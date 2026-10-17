<?php

/**
 * Addons ROG - Administration activity feed
 *
 * - Filter and present audited actions with actor and target references.
 * - Return paginated activity summaries or a spreadsheet-safe CSV export.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{Validator as V, Response};

class ActivityService extends Service {

    // =========================================================================
    // ACTIVITY GROUPS AND FILTERS
    // =========================================================================

    public const GROUPS = [
        'uploads' => ['upload', 'approve'], 'comments' => ['comment'], 'signUps' => ['account'],
        'requests' => ['request'], 'reports' => ['report'],
        'removals' => ['delete', 'reject', 'ban', 'suspend', 'quarantine'],
    ];

    /**
     * Use the full time-window predicate for summaries and detailed filters for
     * result rows.
     */
    private function filters(bool $all = false): array {
        $q = $this->request->query;
        $days = (int)rtrim(V::choice($q['range'] ?? '7d', 'range', ['7d', '30d', '90d']), 'd');
        $params = ['days' => $days]; $where = [
            "e.occurred_at>now()-make_interval(days=>:days)",
            "e.kind IN ('upload','approve','comment','account','request','report',"
                . "'delete','reject','ban','suspend','quarantine','roleChange')",
        ];
        if ($all) return [implode(' AND ', $where), $params];
        if (isset($q['group'])) {
            $group = V::choice($q['group'], 'group', array_keys(self::GROUPS));
            $where[] = "e.kind IN ('" . implode("','", self::GROUPS[$group]) . "')";
        }
        $who = V::choice($q['who'] ?? $q['actor'] ?? 'all', 'who', ['all', 'everyone', 'staff', 'users']);
        if ($who === 'staff') $where[] = "(e.actor_id IS NULL OR "
            . "e.data->>'actorRole' IN ('moderator','founder','system'))";
        if ($who === 'users') $where[] = "e.actor_id IS NOT NULL AND "
            . "coalesce(e.data->>'actorRole','player') NOT IN ('moderator','founder','system')";
        if (isset($q['actorId'])) {
            $params['actor'] = V::integer($q['actorId'], 'actorId'); $where[] = 'e.actor_id=:actor';
        }
        if (isset($q['q'])) {
            $params['q'] = '%' . V::text($q['q'], 'q', 1, 100) . '%';
            $where[] = "(e.data->>'actorName' ILIKE :q OR e.data->>'targetLabel' ILIKE :q)";
        }
        return [implode(' AND ', $where), $params];
    }

    // =========================================================================
    // EVENT PRESENTATION
    // =========================================================================

    public function present(array $row): array {
        $data = $this->json($row['data']);
        $actor = $this->user_ref($row['actor_id']) ?? ['id' => null, 'name' => 'system', 'color' => '#75849b'];
        $actor['role'] = $data['actorRole'] ?? 'system';
        $fields = ['actorName', 'actorRole', 'targetKind', 'targetId', 'targetLabel', 'detail'];
        return [
            'id' => $this->external_id($row['id']), 'kind' => $row['kind'], 'actor' => $actor,
            'target' => [
                'kind' => $data['targetKind'] ?? 'account', 'id' => $this->external_id($data['targetId'] ?? null),
                'label' => $data['targetLabel'] ?? '',
            ],
            'detail' => $data['detail'] ?? null, 'context' => (object)array_diff_key($data, array_flip($fields)),
            'createdAt' => $this->date($row['occurred_at']),
        ];
    }

    // =========================================================================
    // PAGINATED FEED AND SUMMARY COUNTS
    // =========================================================================

    public function listing(): Response {
        if (isset($this->request->query['before'])) $this->request->query['cursor'] = $this->request->query['before'];
        [$limit, $offset] = $this->pagination(50, 100);
        [$where, $params] = $this->filters(); [$all, $all_params] = $this->filters(true);

        $query = 'SELECT e.* FROM events e WHERE ' . $where;
        $query .= ' ORDER BY occurred_at DESC,id DESC LIMIT :limit OFFSET :offset';
        $rows = $this->db()->all($query, $params + ['limit' => $limit, 'offset' => $offset]);
        $total = (int)$this->db()->value('SELECT count(*) FROM events e WHERE ' . $where, $params);

        $query = 'SELECT kind,count(*)::int AS count FROM events e WHERE ' . $all . ' GROUP BY kind';
        // Group totals and top actors describe the entire selected time window.
        $kind_counts = array_column($this->db()->all($query, $all_params), 'count', 'kind'); $groups = [];
        foreach (self::GROUPS as $group => $kinds) {
            $groups[$group] = array_sum(array_intersect_key($kind_counts, array_flip($kinds)));
        }
        $actors = []; $query = 'SELECT actor_id,count(*)::int AS count FROM events e WHERE ' . $all;
        $query .= ' GROUP BY actor_id ORDER BY count(*) DESC LIMIT 5';
        foreach ($this->db()->all($query, $all_params) as $actor) {
            $query = 'SELECT kind,count(*)::int AS count FROM events e WHERE ' . $all;
            $query .= ' AND actor_id IS NOT DISTINCT FROM CAST(:actor AS bigint) GROUP BY kind';
            $counts = $this->db()->all($query, $all_params + ['actor' => $actor['actor_id']]);
            $kinds = array_column($counts, 'count', 'kind');
            $ref = $this->user_ref($actor['actor_id']) ?? ['id' => null, 'name' => 'system', 'color' => '#75849b'];
            $ref['role'] = $actor['actor_id'] ? $this->db()->value(
                'SELECT role FROM account_state WHERE id=:id', ['id' => $actor['actor_id']]
            ) : 'system';
            $actors[] = [
                'actor' => $ref, 'count' => $actor['count'], 'kindCounts' => (object)$kinds,
                'accountId' => $ref['id'], 'username' => $ref['name'], 'kinds' => array_keys($kinds),
            ];
        }
        return $this->page(array_map(fn($r) => $this->present($r), $rows), $total, $offset, $limit, [
            'nextCursor' => $offset + count($rows) < $total ? base64_encode((string)($offset + count($rows))) : null,
            'groupCounts' => $groups, 'topActors' => $actors, 'mostActive' => $actors,
        ]);
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    public function export(): Response {
        [$where, $params] = $this->filters(); $stream = fopen('php://temp/maxmemory:2097152', 'w+');
        $columns = [
            'id', 'createdAt', 'kind', 'actorId', 'actorName',
            'actorRole', 'targetKind', 'targetId', 'targetLabel', 'detail',
        ];
        fputcsv($stream, $columns, ',', '"', '');
        $query = 'SELECT e.* FROM events e WHERE ' . $where . ' ORDER BY occurred_at DESC,id DESC';
        $statement = $this->db()->query($query, $params);
        while ($row = $statement->fetch()) {
            $event = $this->present($row); $values = [
                $event['id'], $event['createdAt'], $event['kind'],
                $event['actor']['id'], $event['actor']['name'], $event['actor']['role'],
                $event['target']['kind'], $event['target']['id'], $event['target']['label'], $event['detail'],
            ];
            // Prefix formula-like strings so spreadsheet applications treat
            // them as text.
            foreach ($values as &$value) {
                if (is_string($value) && preg_match('/^[=+@\-\t\r\n]/', $value)) $value = "'" . $value;
            }
            unset($value); fputcsv($stream, $values, ',', '"', '');
        }
        rewind($stream); $response = new Response(null, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="activity.csv"',
        ]);
        $response->raw = stream_get_contents($stream); fclose($stream); return $response;
    }
}
