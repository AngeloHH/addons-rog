<?php

/**
 * Addons ROG - Community addon requests
 *
 * - List requests with votes, followers, and linked submission visibility.
 * - Coordinate request transitions, fulfillment, and author notifications.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{ApiException, Validator as V, Response};

class RequestService extends Service {

    // =========================================================================
    // REQUEST PRESENTATION AND LISTING
    // =========================================================================

    public const STATES = ['open', 'volunteered', 'inQueue', 'fulfilled', 'closed'];

    /**
     * Include viewer interactions while exposing private submission IDs only to
     * owners or staff.
     */
    public function present(array $row): array {
        $query = 'SELECT kind,count(*)::int AS count FROM interactions WHERE request_id=:id GROUP BY kind';
        $counts = array_column($this->db()->all($query, ['id' => $row['id']]), 'count', 'kind');
        $query = 'SELECT kind FROM interactions WHERE request_id=:id AND actor_id=:user';
        $mine = array_column($this->db()->all($query, ['id' => $row['id'], 'user' => $this->user_id()]), 'kind');
        $query = "SELECT id,owner_id FROM submissions WHERE request_id=:id AND status IN ('draft','pending') ";
        $query .= "ORDER BY (status='pending') DESC,updated_at DESC LIMIT 1";
        $submission = $this->db()->one($query, ['id' => $row['id']]);
        $addon = $row['fulfilled_addon_id'] ? $this->db()->one(
            'SELECT slug,name,category_id AS "categoryId" FROM addons WHERE id=:id',
            ['id' => $row['fulfilled_addon_id']]
        ) : null;
        if ($addon) $addon['expansionId'] = $row['expansion_id'];
        return [
            'id' => $this->external_id($row['id']), 'addonName' => $row['addon_name'],
            'expansionId' => $row['expansion_id'],
            'text' => $row['details'], 'details' => $row['details'], 'state' => $row['state'],
            'votes' => $counts['request_vote'] ?? 0, 'voteCount' => $counts['request_vote'] ?? 0,
            'followers' => $counts['request_follow'] ?? 0, 'followerCount' => $counts['request_follow'] ?? 0,
            'replies' => 0, 'replyCount' => 0, 'linkedAddonId' => $this->external_id($row['fulfilled_addon_id']),
            'linkedSubmissionId' => $submission && ($this->staff() || (int)$submission['owner_id'] === $this->user_id())
                ? $this->external_id($submission['id']) : null,
            'author' => $this->user_ref($row['author_id']), 'addon' => $addon,
            'submissionId' => $submission && ($this->staff() || (int)$submission['owner_id'] === $this->user_id())
                ? $this->external_id($submission['id']) : null,
            'createdAt' => $this->date($row['created_at']), 'updatedAt' => $this->date($row['updated_at']),
            'viewer' => $this->user_id() ? [
                'voted' => in_array('request_vote', $mine, true),
                'following' => in_array('request_follow', $mine, true),
            ] : null,
        ];
    }

    public function preview(int $limit): array {
        $query = "SELECT * FROM requests WHERE state<>'closed' ORDER BY created_at DESC,id DESC LIMIT :limit";
        return array_map(fn($r) => $this->present($r), $this->db()->all($query, ['limit' => $limit]));
    }

    public function listing(): Response {
        [$limit, $offset] = $this->pagination(20, 50); $q = $this->request->query; $base = []; $params = [];
        if (isset($q['q'])) {
            $params['q'] = '%' . V::text($q['q'], 'q', 1, 100) . '%';
            $base[] = '(r.addon_name ILIKE :q OR u.username ILIKE :q)';
        }
        $counts_where = $base ? ' WHERE ' . implode(' AND ', $base) : '';
        $state_counts = array_fill_keys(self::STATES, 0);
        $query = 'SELECT r.state,count(*)::int AS count FROM requests r JOIN accounts u ON u.id=r.author_id';
        $query .= $counts_where . ' GROUP BY r.state';
        foreach ($this->db()->all($query, $params) as $count) $state_counts[$count['state']] = $count['count'];

        $query = 'SELECT r.expansion_id,count(*)::int AS count FROM requests r JOIN accounts u ON u.id=r.author_id';
        $query .= $counts_where . ' GROUP BY r.expansion_id';
        $exp_counts = array_column($this->db()->all($query, $params), 'count', 'expansion_id');
        if (isset($q['state'])) {
            $params['state'] = V::choice($q['state'], 'state', self::STATES); $base[] = 'r.state=:state';
        } else $base[] = "r.state<>'closed'";
        if (isset($q['expansion']) || isset($q['expansionId'])) {
            $params['expansion'] = $this->expansion($q['expansion'] ?? $q['expansionId']);
            $base[] = 'r.expansion_id=:expansion';
        }
        $sort = V::choice($q['sort'] ?? 'votes', 'sort', ['votes', 'newest', 'oldest']);
        $order = match($sort) {
            'oldest' => 'r.created_at ASC,r.id ASC', 'newest' => 'r.created_at DESC,r.id DESC',
            default => "(SELECT count(*) FROM interactions i WHERE i.request_id=r.id "
                . "AND i.kind='request_vote') DESC,r.id DESC",
        };
        $sql = ' FROM requests r JOIN accounts u ON u.id=r.author_id WHERE ' . implode(' AND ', $base);
        $query = 'SELECT r.*' . $sql . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset';
        return $this->page(
            array_map(fn($r) => $this->present($r), $this->db()->all(
                $query, $params + ['limit' => $limit, 'offset' => $offset]
            )),
            (int)$this->db()->value('SELECT count(*)' . $sql, $params), $offset, $limit,
            ['stateCounts' => $state_counts, 'expansionCounts' => (object)$exp_counts]
        );
    }

    // =========================================================================
    // REQUEST CREATION AND INTERACTIONS
    // =========================================================================

    public function create(): array {
        $this->rate('request:' . $this->user_id(), 5, 86400);
        $name = V::text($this->request->input('addonName'), 'addonName', 2, 60);
        $exp = $this->expansion($this->request->input('expansionId'));
        $text = V::text($this->request->input('text'), 'details', 10, 500);
        return $this->db()->transaction(function () use ($name, $exp, $text) {
            $query = 'INSERT INTO requests(author_id,addon_name,expansion_id,details) ';
            $query .= 'VALUES (:user,:name,:expansion,:text) RETURNING *';
            $row = $this->row($query, [
                'user' => $this->user_id(), 'name' => $name, 'expansion' => $exp, 'text' => $text,
            ]);
            // The request author starts as both a voter and a follower.
            foreach (['request_vote', 'request_follow'] as $kind) $this->db()->query(
                'INSERT INTO interactions(actor_id,kind,request_id) VALUES (:user,:kind,:id)',
                ['user' => $this->user_id(), 'kind' => $kind, 'id' => $row['id']]
            );
            $this->audit('request', 'request', $row['id'], ['targetLabel' => $name]);
            return ['request' => $this->present($row)];
        });
    }

    public function vote(): array { return $this->toggle('request_vote', 'voted'); }
    public function follow(): array { return $this->toggle('request_follow', 'following'); }

    /**
     * Apply the requested interaction state idempotently; completed requests
     * cannot change votes.
     */
    private function toggle(string $kind, string $field): array {
        $id = $this->id('requestId'); $active = V::boolean($this->request->input($field), $field);
        return $this->db()->transaction(function () use ($kind, $field, $id, $active) {
            $row = $this->row('SELECT * FROM requests WHERE id=:id FOR SHARE', ['id' => $id]);
            if (
                $kind === 'request_vote' && in_array($row['state'], ['fulfilled', 'closed'], true)
            ) throw new ApiException(409, 'INVALID_STATE');
            $this->db()->lock('interaction:' . $this->user_id() . ':' . $kind . ':' . $id);
            $params = ['user' => $this->user_id(), 'kind' => $kind, 'id' => $id];
            if ($active) $this->db()->query(
                'INSERT INTO interactions(actor_id,kind,request_id) VALUES (:user,:kind,:id) ON CONFLICT DO NOTHING',
                $params
            );
            else $this->db()->query(
                'DELETE FROM interactions WHERE actor_id=:user AND kind=:kind AND request_id=:id', $params
            );
            $query = 'SELECT count(*) FROM interactions WHERE request_id=:id AND kind=:kind';
            $count = (int)$this->db()->value($query, ['id' => $id, 'kind' => $kind]);
            return [
                'requestId' => $this->external_id($id), 'active' => $active, $field => $active,
                ($kind === 'request_vote' ? 'votes' : 'followers') => $count,
                ($kind === 'request_vote' ? 'voteCount' : 'followerCount') => $count,
            ];
        });
    }

    // =========================================================================
    // REQUEST STATE TRANSITIONS
    // =========================================================================

    /**
     * Validate a state change, record its audit event, and optionally notify
     * followers.
     */
    public function transition(int $id, string $state, ?int $addon = null, bool $notify = true): array {
        $row = $this->row('SELECT * FROM requests WHERE id=:id FOR UPDATE', ['id' => $id]);
        if ($row['state'] === $state) return $row;
        $allowed = [
            'open' => ['volunteered', 'inQueue', 'fulfilled', 'closed'],
            'volunteered' => ['open', 'inQueue', 'fulfilled', 'closed'],
            'inQueue' => ['open', 'volunteered', 'fulfilled'],
        ];
        if (
            !in_array($state, $allowed[$row['state']] ?? [], true) || ($state === 'fulfilled' && !$addon)
        ) throw new ApiException(409, 'INVALID_STATE');
        $query = 'UPDATE requests SET state=:state,fulfilled_addon_id=:addon WHERE id=:id RETURNING *';
        $updated = $this->row($query, ['state' => $state, 'addon' => $addon, 'id' => $id]);
        $event = $this->audit('request', 'request', $id, [
            'targetLabel' => $row['addon_name'], 'before' => $row['state'], 'after' => $state,
        ]);
        if ($notify) {
            $query = "SELECT actor_id FROM interactions WHERE request_id=:id AND kind='request_follow'";
            foreach ($this->db()->all($query, ['id' => $id]) as $user) (new NotificationService($this->app))->account(
                (int)$user['actor_id'], 'Request updated', $row['addon_name'] . ': ' . $state,
                'request:' . $event . ':' . $user['actor_id']
            );
        }
        return $updated;
    }

    /**
     * Derive an unfinished request state from linked drafts and pending
     * submissions.
     */
    public function reconcile(int $id): void {
        $row = $this->row('SELECT * FROM requests WHERE id=:id FOR UPDATE', ['id' => $id]);
        if (in_array($row['state'], ['fulfilled', 'closed'], true)) return;
        $query = "SELECT status FROM submissions WHERE request_id=:id AND status IN ('draft','pending') ";
        $query .= "ORDER BY (status='pending') DESC LIMIT 1";
        $state = $this->db()->value($query, ['id' => $id]);
        $this->transition($id, $state === 'pending' ? 'inQueue' : ($state === 'draft' ? 'volunteered' : 'open'));
    }

    // =========================================================================
    // STAFF LINKING AND CLOSURE
    // =========================================================================

    public function action(): array {
        $id = $this->id('requestId'); $body = $this->request->body;
        $action = $body['action'] ?? (isset($body['linkedSubmissionId']) || isset($body['linkedAddonId'])
            ? 'link' : (($body['state'] ?? '') === 'closed' ? 'close' : 'invalid'));
        V::choice($action, 'action', ['link', 'ping', 'close']);
        if ($action === 'ping') {
            $this->notify_authors();
            return ['request' => $this->present($this->row('SELECT * FROM requests WHERE id=:id', ['id' => $id]))];
        }
        return $this->db()->transaction(function () use ($id, $body, $action) {
            $request = $this->row('SELECT * FROM requests WHERE id=:id FOR UPDATE', ['id' => $id]);
            $notify = isset($body['notifyFollowers']) ? V::boolean($body['notifyFollowers'], 'notifyFollowers') : true;
            if ($action === 'close') {
                if (
                    !in_array($request['state'], ['open', 'volunteered'], true)
                ) throw new ApiException(409, 'INVALID_STATE');
                $row = $this->transition($id, 'closed', null, $notify);
            } else {
                $sid = $body['submissionId'] ?? $body['linkedSubmissionId'] ?? null;
                $aid = $body['linkedAddonId'] ?? null; $slug = $body['addonSlug'] ?? null;
                // A link must select exactly one submission or one published
                // addon.
                if (($sid !== null ? 1 : 0) + ($aid !== null || $slug !== null ? 1 : 0) !== 1) V::fail('link');
                if ($sid !== null) {
                    $submission = $this->row('SELECT * FROM submissions WHERE id=:id FOR UPDATE', [
                        'id' => V::integer($sid, 'submissionId'),
                    ]);
                    if (
                        !in_array($submission['status'], ['draft', 'pending'], true)
                        || ($submission['request_id'] !== null && (int)$submission['request_id'] !== $id)
                        || ($submission['expansion_id'] !== null
                            && $submission['expansion_id'] !== $request['expansion_id'])
                    ) throw new ApiException(409, 'INVALID_STATE');
                    $this->db()->query('UPDATE submissions SET request_id=:request WHERE id=:id', [
                        'request' => $id, 'id' => $sid,
                    ]);
                    $target = $submission['status'] === 'pending' ? 'inQueue' : 'volunteered';
                    if (isset($body['state']) && $body['state'] !== $target) V::fail('state');
                    $row = $this->transition($id, $target, null, $notify);
                } else {
                    $addon = $slug !== null
                        ? (new CatalogService($this->app))->find(V::text($slug, 'addonSlug', 1, 200))
                        : $this->row('SELECT * FROM addons WHERE id=:id AND withdrawn_at IS NULL', [
                            'id' => V::integer($aid, 'linkedAddonId'),
                        ]);
                    $query = 'SELECT EXISTS(SELECT 1 FROM published_versions ';
                    $query .= 'WHERE addon_id=:id AND expansion_id=:expansion)';
                    if (!$this->db()->value($query, [
                        'id' => $addon['id'], 'expansion' => $request['expansion_id'],
                    ])) V::fail('addonSlug');
                    if (isset($body['state']) && $body['state'] !== 'fulfilled') V::fail('state');
                    $row = $this->transition($id, 'fulfilled', (int)$addon['id'], $notify);
                }
            }
            return ['request' => $this->present($row)];
        });
    }

    // =========================================================================
    // AUTHOR NOTIFICATION BATCH
    // =========================================================================

    public function notify_authors(): array {
        $id = $this->id('requestId'); $request = $this->row('SELECT * FROM requests WHERE id=:id', ['id' => $id]);
        $message = $this->request->input('message', 'Please review the community request: ' . $request['addon_name']);
        V::text($message, 'message', 1, 4000);
        $expansion = $this->request->input('expansionId', $request['expansion_id']); $this->expansion($expansion);
        return $this->db()->transaction(function () use ($id, $request, $message, $expansion) {
            // Limit each request to one author notification batch per 24-hour
            // window.
            $this->db()->lock('ping:' . $id);
            $query = "SELECT EXISTS(SELECT 1 FROM events WHERE kind='request' AND data->>'action'='ping' ";
            $query .= "AND data->>'targetId'=:id AND occurred_at>now()-interval '24 hours')";
            if (
                $this->db()->value($query, ['id' => (string)$id])
            ) throw new ApiException(429, 'RATE_LIMITED', [], [], 86400);
            $event = $this->audit('request', 'request', $id, [
                'action' => 'ping', 'targetLabel' => $request['addon_name'],
            ]);
            $query = 'SELECT DISTINCT a.owner_id FROM addons a JOIN published_versions v ON v.addon_id=a.id ';
            $query .= 'JOIN accounts u ON u.id=a.owner_id WHERE v.expansion_id=:expansion AND u.banned_at IS NULL';
            $users = $this->db()->all($query, ['expansion' => $expansion]);
            foreach ($users as $user) (new NotificationService($this->app))->account(
                (int)$user['owner_id'], 'Community request', $message, 'ping:' . $event . ':' . $user['owner_id']
            );
            return [
                'notificationId' => $this->external_id($event), 'recipientCount' => count($users), 'status' => 'queued',
            ];
        });
    }
}
