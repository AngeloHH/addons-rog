<?php

/**
 * Addons ROG - Moderation reports
 *
 * - Group reporter statements by target and report type.
 * - Apply moderation outcomes, record audit events, and notify content owners.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{ApiException, Validator as V, Response};

class ReportService extends Service {

    // =========================================================================
    // REPORT TARGETS AND PRESENTATION
    // =========================================================================

    public const SEVERITY = ['file' => 'critical', 'credit' => 'high', 'comment' => 'medium', 'listing' => 'low'];

    /**
     * Resolve a report target and optionally require it to be publicly
     * available.
     */
    private function target(string $kind, int $id, bool $public = false): array {
        if ($kind === 'addon') {
            $query = 'SELECT a.id,a.id AS addon_id,a.owner_id,a.name AS label,a.slug,a.category_id,';
            $query .= 'NULL::text AS expansion_id FROM addons a WHERE a.id=:id';
            if ($public) $query .= ' AND a.withdrawn_at IS NULL '
                . 'AND EXISTS(SELECT 1 FROM published_versions v WHERE v.addon_id=a.id)';
            $row = $this->row($query, ['id' => $id]);
        } elseif ($kind === 'version') {
            $query = "SELECT s.id,s.addon_id,s.owner_id,coalesce(a.name,s.fields->>'name') AS label,";
            $query .= 'a.slug,a.category_id,s.expansion_id FROM submissions s ';
            $query .= 'LEFT JOIN addons a ON a.id=s.addon_id WHERE s.id=:id';
            if ($public) $query .= ' AND EXISTS(SELECT 1 FROM published_versions v WHERE v.id=s.id)';
            $row = $this->row($query, ['id' => $id]);
        } else {
            $query = 'SELECT c.id,c.addon_id,c.author_id AS owner_id,a.name AS label,a.slug,a.category_id,';
            $query .= 'c.expansion_id FROM comments c JOIN addons a ON a.id=c.addon_id WHERE c.id=:id';
            if ($public) $query .= ' AND c.withdrawn_at IS NULL AND a.withdrawn_at IS NULL';
            $row = $this->row($query, ['id' => $id]);
        }
        return $row;
    }

    public function present(array $row): array {
        $kind = $row['addon_id'] !== null ? 'addon' : ($row['submission_id'] !== null ? 'version' : 'comment');
        $id = $row['addon_id'] ?? $row['submission_id'] ?? $row['comment_id']; $target = $this->target($kind, (int)$id);
        $entry = $this->db()->one('SELECT * FROM report_entries WHERE report_id=:id ORDER BY id LIMIT 1', [
            'id' => $row['id'],
        ]);
        $data = $this->json($row['details']);
        return [
            'id' => $this->external_id($row['id']), 'severity' => self::SEVERITY[$row['type']], 'type' => $row['type'],
            'target' => [
                'kind' => $kind, 'id' => $this->external_id($id), 'label' => $target['label'],
                'addonSlug' => $target['slug'], 'expansionId' => $target['expansion_id'],
                'categoryId' => $target['category_id'],
            ],
            'text' => $entry['body'] ?? '',
            'reporter' => $entry && $entry['reporter_id'] !== null ? $this->user_ref($entry['reporter_id'])
                : ['id' => null, 'name' => 'scanner'],
            'otherReporters' => max(0, (int)$this->db()->value(
                'SELECT count(*) FROM report_entries WHERE report_id=:id AND reporter_id IS NOT NULL',
                ['id' => $row['id']]
            ) - ($entry && $entry['reporter_id'] !== null ? 1 : 0)),
            'autoQuarantined' => $data['autoQuarantined'] ?? false, 'uploader' => $this->user_ref($target['owner_id']),
            'ruleId' => $data['ruleId'] ?? null, 'closed' => $row['closed_at'] !== null,
            'status' => $row['closed_at'] !== null ? 'closed' : 'open', 'resolution' => $row['resolution'],
            'createdAt' => $this->date($row['created_at']), 'closedAt' => $this->date($row['closed_at']),
        ];
    }

    // =========================================================================
    // REPORT CREATION AND REPORTER DEDUPLICATION
    // =========================================================================

    public function create(): array {
        $this->rate('report:' . $this->user_id(), 10, 3600); $target = $this->request->input('target');
        if (!is_array($target)) V::fail('target');
        $kind = V::choice($target['kind'] ?? null, 'target.kind', ['addon', 'version', 'comment']);
        $id = V::integer($target['id'] ?? null, 'target.id');
        $type = V::choice($this->request->input('type'), 'type', array_keys(self::SEVERITY));
        $body = V::text($this->request->input('text'), 'text', 10, 1000);
        if (($type === 'comment') !== ($kind === 'comment')) V::fail('type');
        $this->target($kind, $id, true);
        return $this->db()->transaction(function () use ($kind, $id, $type, $body) {
            $column = ['addon' => 'addon_id', 'version' => 'submission_id', 'comment' => 'comment_id'][$kind];
            // Reuse one open report per target/type while preventing concurrent
            // duplicate reports.
            $this->db()->lock('report:' . $kind . ':' . $id . ':' . $type);
            $query = 'SELECT * FROM reports WHERE ' . $column . '=:id AND type=:type AND closed_at IS NULL FOR UPDATE';
            $report = $this->db()->one($query, ['id' => $id, 'type' => $type]);
            if (!$report) $report = $this->row(
                'INSERT INTO reports(type,' . $column . ') VALUES (:type,:id) RETURNING *',
                ['type' => $type, 'id' => $id]
            );
            $query = 'SELECT EXISTS(SELECT 1 FROM report_entries WHERE report_id=:report AND reporter_id=:user)';
            if (
                $this->db()->value($query, ['report' => $report['id'], 'user' => $this->user_id()])
            ) throw new ApiException(409, 'ALREADY_REPORTED');
            $query = 'INSERT INTO report_entries(report_id,reporter_id,body) VALUES (:report,:user,:body)';
            $this->db()->query($query, ['report' => $report['id'], 'user' => $this->user_id(), 'body' => $body]);
            $this->audit('report', 'report', $report['id'], ['targetLabel' => $type]);
            return ['reportId' => $this->external_id($report['id'])];
        });
    }

    // =========================================================================
    // MODERATION QUEUE
    // =========================================================================

    public function listing(): Response {
        [$limit, $offset] = $this->pagination(20, 50); $where = ['true']; $params = []; $q = $this->request->query;
        $filters = ['type' => array_keys(self::SEVERITY), 'severity' => array_values(self::SEVERITY)];
        foreach ($filters as $key => $allowed) {
            if (isset($q[$key])) {
                $value = V::choice($q[$key], $key, $allowed);
                $params[$key] = $key === 'severity' ? array_search($value, self::SEVERITY, true) : $value;
                $where[] = 'r.type=:' . $key;
            }
        }
        $state = $q['state'] ?? $q['status'] ?? null;
        if ($state !== null) {
            V::choice($state, 'state', ['open', 'closed']);
            $where[] = 'r.closed_at IS ' . ($state === 'open' ? 'NULL' : 'NOT NULL');
        }
        if (isset($q['q'])) {
            $params['q'] = '%' . V::text($q['q'], 'q', 1, 100) . '%';
            $where[] = "(coalesce(a.name,va.name,ca.name,'') ILIKE :q OR EXISTS(SELECT 1 FROM report_entries re "
                . 'LEFT JOIN accounts u ON u.id=re.reporter_id '
                . 'WHERE re.report_id=r.id AND (re.body ILIKE :q OR u.username ILIKE :q)))';
        }
        $base = ' FROM reports r LEFT JOIN addons a ON a.id=r.addon_id ';
        $base .= 'LEFT JOIN submissions s ON s.id=r.submission_id LEFT JOIN addons va ON va.id=s.addon_id ';
        $base .= 'LEFT JOIN comments c ON c.id=r.comment_id LEFT JOIN addons ca ON ca.id=c.addon_id WHERE ';
        $base .= implode(' AND ', $where);
        $counts = array_fill_keys(array_values(self::SEVERITY), 0);
        $query = 'SELECT type,count(*)::int AS count FROM reports WHERE closed_at IS NULL GROUP BY type';
        foreach ($this->db()->all($query) as $r) $counts[self::SEVERITY[$r['type']]] = $r['count'];
        $extra = $counts;

        $query = 'SELECT r.*' . $base . " ORDER BY (r.closed_at IS NULL) DESC,CASE r.type WHEN 'file' THEN 0 ";
        $query .= "WHEN 'credit' THEN 1 WHEN 'comment' THEN 2 ELSE 3 END,r.created_at DESC,r.id DESC ";
        $query .= 'LIMIT :limit OFFSET :offset';
        return $this->page(
            array_map(fn($r) => $this->present($r), $this->db()->all(
                $query, $params + ['limit' => $limit, 'offset' => $offset]
            )),
            (int)$this->db()->value('SELECT count(*)' . $base, $params), $offset, $limit, ['severityCounts' => $extra]
        );
    }

    // =========================================================================
    // RESOLUTION AND OWNER NOTIFICATIONS
    // =========================================================================

    public function action(): array {
        $action = V::choice($this->request->input('action'), 'action', ['warn', 'takedown', 'dismiss', 'reopen']);
        $note = $this->request->input('note');
        if ($note !== null) V::text($note, 'note', 0, 4000);
        return $this->db()->transaction(function () use ($action, $note) {
            $row = $this->row('SELECT * FROM reports WHERE id=:id FOR UPDATE', ['id' => $this->id('reportId')]);
            if (($action === 'reopen') !== ($row['closed_at'] !== null)) throw new ApiException(409, 'INVALID_STATE');
            $kind = $row['addon_id'] ? 'addon' : ($row['submission_id'] ? 'version' : 'comment');
            $id = (int)($row['addon_id'] ?? $row['submission_id'] ?? $row['comment_id']);
            $target = $this->target($kind, $id);
            // Withdraw the target while retaining its record and moderation
            // history.
            if ($action === 'takedown') {
                $table = ['addon' => 'addons', 'version' => 'submissions', 'comment' => 'comments'][$kind];
                $query = 'UPDATE ' . $table . ' SET withdrawn_at=COALESCE(withdrawn_at,now()) WHERE id=:id';
                $this->db()->query($query, ['id' => $id]);
            }
            $data = $this->json($row['details']); $data['note'] = $note; $data['moderatorId'] = $this->user_id();
            $query = 'UPDATE reports SET resolution=:resolution,closed_at=' . ($action === 'reopen' ? 'NULL' : 'now()');
            $query .= ',details=CAST(:details AS jsonb) WHERE id=:id RETURNING *';
            $row = $this->row($query, [
                'resolution' => $action === 'reopen' ? null : $action,
                'details' => $this->encode($data), 'id' => $row['id'],
            ]);
            $event = $this->audit($action === 'takedown' ? 'delete' : 'report', 'report', $row['id'], [
                'action' => $action, 'ownerId' => (int)$target['owner_id'],
                'targetLabel' => $target['label'], 'detail' => $note,
            ]);
            // Notify the owner only for warnings and takedowns.
            if (in_array($action, ['warn', 'takedown'], true)) (new NotificationService($this->app))->account(
                (int)$target['owner_id'], 'Moderation: ' . $action, $note ?? $target['label'], 'report:' . $event
            );
            return ['report' => $this->present($row)];
        });
    }
}
