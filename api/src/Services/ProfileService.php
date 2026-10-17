<?php

/**
 * Addons ROG - Public profiles and author activity
 *
 * - Expose published addons, public reviews, activity, and author statistics.
 * - Manage follows and derive profile progress from stored calculation rules.
 */

declare(strict_types=1);

namespace Rog\Services;

use DateTimeImmutable;
use Rog\Database\Calendar;
use Rog\Http\{ApiException, Validator as V, Response};

class ProfileService extends Service {

    // =========================================================================
    // PUBLIC IDENTITY AND ADDON LOOKUP
    // =========================================================================

    /**
     * Resolve a verified visible account; the me alias requires an
     * authenticated viewer.
     */
    private function user(): array {
        $name = V::text($this->request->param('username'), 'username', 1, 32);
        if ($name === 'me') {
            if (!$this->app->user) throw new ApiException(401, 'UNAUTHENTICATED');
            $name = $this->app->user['username'];
        }
        $query = 'SELECT * FROM account_state WHERE lower(username)=lower(:name) ';
        $query .= 'AND banned_at IS NULL AND verified_at IS NOT NULL';
        return $this->row($query, ['name' => $name]);
    }

    private function addon_rows(int $id, int $limit, int $offset = 0, bool $recent = false): array {
        $query = 'SELECT a.*,m.downloads,m.rating,m.votes FROM addons a ';
        $query .= 'LEFT JOIN catalog_metrics m ON m.addon_id=a.id ';
        $query .= 'WHERE a.owner_id=:id AND a.withdrawn_at IS NULL ';
        $query .= 'AND EXISTS(SELECT 1 FROM published_versions v WHERE v.addon_id=a.id) ORDER BY ';
        $query .= $recent ? '(SELECT max(released_at) FROM published_versions WHERE addon_id=a.id) DESC'
            : 'm.downloads DESC NULLS LAST';
        $query .= ',a.id DESC LIMIT :limit OFFSET :offset';
        return $this->db()->all($query, ['id' => $id, 'limit' => $limit, 'offset' => $offset]);
    }

    // =========================================================================
    // PUBLIC ADDONS, JOURNAL, AND REVIEWS
    // =========================================================================

    public function addons(): Response {
        $user = $this->user(); [$limit, $offset] = $this->pagination(24, 48);
        $rows = $this->addon_rows((int)$user['id'], $limit, $offset, true);
        $query = 'SELECT count(*) FROM addons a WHERE owner_id=:id AND withdrawn_at IS NULL ';
        $query .= 'AND EXISTS(SELECT 1 FROM published_versions v WHERE v.addon_id=a.id)';
        $count = (int)$this->db()->value($query, ['id' => $user['id']]);
        return $this->page(
            array_map(fn($a) => (new CatalogService($this->app))->summary($a), $rows), $count, $offset, $limit
        );
    }

    /**
     * Expose approval and comment activity without private audit details.
     */
    public function journal(): Response {
        $user = $this->user(); [$limit, $offset] = $this->pagination(20, 50);
        $where = " FROM events WHERE (actor_id=:id OR data->>'ownerId'=:text) AND kind IN ('approve','comment')";
        $params = ['id' => $user['id'], 'text' => (string)$user['id']];
        $query = 'SELECT *' . $where . ' ORDER BY occurred_at DESC,id DESC LIMIT :limit OFFSET :offset';
        $items = array_map(function ($e) {
            $data = $this->json($e['data']);
            return [
                'id' => $this->external_id($e['id']), 'type' => $e['kind'] === 'approve' ? 'shipped' : 'replied',
                'target' => [
                    'kind' => 'addon', 'id' => (string)($data['targetId'] ?? ''),
                    'label' => $data['targetLabel'] ?? '', 'addonSlug' => $data['addonSlug'] ?? null,
                ],
                'detail' => null, 'note' => null, 'createdAt' => $this->date($e['occurred_at']),
            ];
        }, $this->db()->all($query, $params + ['limit' => $limit, 'offset' => $offset]));
        return $this->page($items, (int)$this->db()->value('SELECT count(*)' . $where, $params), $offset, $limit);
    }

    public function reviews(): Response {
        $user = $this->user(); [$limit, $offset] = $this->pagination(10, 50);
        $where = ' FROM current_ratings c JOIN addons a ON a.id=c.addon_id ';
        $where .= 'WHERE c.author_id=:id AND a.owner_id<>:id AND a.withdrawn_at IS NULL';
        $params = ['id' => $user['id']]; $query = 'SELECT c.*,a.slug,a.name,a.category_id' . $where;
        $query .= ' ORDER BY c.created_at DESC,c.id DESC LIMIT :limit OFFSET :offset';
        $items = array_map(fn($r) => [
            'id' => $this->external_id($r['id']),
            'addon' => [
                'slug' => $r['slug'], 'name' => $r['name'],
                'expansionId' => $r['expansion_id'], 'categoryId' => $r['category_id'],
            ],
            'rating' => (int)$r['rating'], 'text' => $r['body'], 'helpful' => 0,
            'createdAt' => $this->date($r['created_at']),
        ], $this->db()->all($query, $params + ['limit' => $limit, 'offset' => $offset]));
        return $this->page($items, (int)$this->db()->value('SELECT count(*)' . $where, $params), $offset, $limit);
    }

    // =========================================================================
    // FOLLOW STATE
    // =========================================================================

    public function follow(): array {
        $user = $this->user();
        if ((int)$user['id'] === $this->user_id()) V::fail('username');
        $active = V::boolean($this->request->input('following'), 'following');
        return $this->db()->transaction(function () use ($user, $active) {
            // Serialize the viewer/author pair so repeated follow requests
            // remain idempotent.
            $this->db()->lock('follow:' . $this->user_id() . ':' . $user['id']);
            $params = ['actor' => $this->user_id(), 'target' => $user['id']];
            if ($active) $this->db()->query(
                "INSERT INTO interactions(actor_id,kind,target_user_id) "
                    . "VALUES (:actor,'follow_user',:target) ON CONFLICT DO NOTHING", $params
            );
            else $this->db()->query(
                "DELETE FROM interactions WHERE actor_id=:actor AND target_user_id=:target AND kind='follow_user'",
                $params
            );
            return [
                'following' => $active, 'followers' => (int)$this->db()->value(
                    "SELECT count(*) FROM interactions WHERE target_user_id=:id AND kind='follow_user'",
                    ['id' => $user['id']]
                ),
            ];
        });
    }

    // =========================================================================
    // PROFILE STATISTICS AND PROGRESS
    // =========================================================================

    public function show(): array {
        $user = $this->user(); $id = (int)$user['id']; $profile = $this->json($user['profile']);
        $query = "SELECT count(*)::int AS count,count(*) FILTER(WHERE published_at>=date_trunc('year',now()))::int ";
        $query .= 'AS year FROM addons a WHERE owner_id=:id AND withdrawn_at IS NULL ';
        $query .= 'AND EXISTS(SELECT 1 FROM published_versions v WHERE v.addon_id=a.id)';
        $addons = $this->row($query, ['id' => $id]);
        $query = 'SELECT count(*)::int AS count,max(released_at) AS latest FROM published_versions WHERE owner_id=:id';
        $versions = $this->row($query, ['id' => $id]);

        $query = "SELECT count(*)::int AS count,count(*) FILTER(WHERE e.occurred_at>now()-interval '30 days')::int ";
        $query .= 'AS recent FROM counted_downloads e JOIN submissions s ON s.id=e.submission_id ';
        $query .= 'JOIN addons a ON a.id=s.addon_id WHERE a.owner_id=:id';
        $downloads = $this->row($query, ['id' => $id]);

        $query = 'SELECT round(avg(c.rating),1) AS rating,count(*)::int AS count FROM current_ratings c ';
        $query .= 'JOIN addons a ON a.id=c.addon_id WHERE a.owner_id=:id';
        $ratings = $this->row($query, ['id' => $id]);
        $query = "SELECT count(*)::int AS count,count(*) FILTER(WHERE created_at>now()-interval '30 days')::int ";
        $query .= "AS recent FROM interactions WHERE target_user_id=:id AND kind='follow_user'";
        $followers = $this->row($query, ['id' => $id]);
        $query = 'SELECT count(*) FROM comments WHERE author_id=:id AND withdrawn_at IS NULL';
        $replies = (int)$this->db()->value($query, ['id' => $id]);
        $stats = [
            'addons' => $addons['count'], 'addonsThisYear' => $addons['year'],
            'downloads' => $downloads['count'], 'downloads30d' => $downloads['recent'],
            'averageRating' => $ratings['rating'] === null ? null : (float)$ratings['rating'],
            'reviewsReceived' => $ratings['count'], 'followers' => $followers['count'],
            'followers30d' => $followers['recent'], 'versions' => $versions['count'],
            'lastVersionAt' => $this->date($versions['latest']), 'replies' => $replies,
        ];
        // Calculate level and skill progress from published versions and
        // visible comments.
        $rules = $this->config('calculationRules');
        $points = $versions['count'] * ($rules['pointsPerVersion'] ?? 100)
            + $replies * ($rules['pointsPerComment'] ?? 5);
        $step = max(1, (int)($rules['pointsPerLevel'] ?? 100)); $level = 1 + (int)floor($points / $step);
        $identity = $this->user_ref($id) + [
            'role' => $user['role'], 'titleId' => $profile['titleId'] ?? null, 'level' => $level,
            'realm' => $profile['realm'] ?? null, 'joinedAt' => $this->date($user['created_at']),
            'online' => $user['last_seen_at'] !== null && strtotime($user['last_seen_at']) > time() - 300,
            'badge' => $profile['badge'] ?? null, 'spec' => $profile['spec'] ?? null,
            'skill' => ['value' => $points % $step, 'max' => $step],
            'reputation' => [
                'value' => $followers['count'], 'max' => max(100, $followers['count']),
                'standing' => match(true) {
                    $followers['count'] >= 1000 => 'exalted', $followers['count'] >= 500 => 'revered',
                    $followers['count'] >= 100 => 'honored', $followers['count'] >= 10 => 'friendly',
                    default => 'neutral',
                },
            ],
        ];
        // Build 28 daily activity buckets starting from a Monday-aligned
        // boundary.
        $heat = Calendar::counts(
            $this->db(), 'events', "actor_id=:id AND kind IN ('approve','upload','comment','request')", ['id' => $id],
            (new DateTimeImmutable('monday this week'))->setTime(0, 0)->modify('-21 days'), 28, '+1 day', 'date'
        );
        $query = 'SELECT c.*,a.slug FROM comments c JOIN addons a ON a.id=c.addon_id ';
        $query .= 'WHERE a.owner_id=:id AND c.author_id<>:id AND c.withdrawn_at IS NULL AND a.withdrawn_at IS NULL ';
        $query .= 'ORDER BY c.created_at DESC,c.id DESC LIMIT 1';
        $last = $this->db()->one($query, ['id' => $id]); $achievements = [];
        // Use configured metric targets while retaining explicitly recorded
        // completion dates.
        foreach ($this->config('achievementDefinitions') as $key => $definition) {
            $value = $stats[$definition['metric']] ?? 0; $max = $definition['target'];
            $achievements[] = [
                'id' => $key, 'points' => $definition['points'] ?? 0, 'feat' => $definition['feat'] ?? false,
                'completedAt' => $profile['achievements'][$key]['completedAt'] ?? null,
                'progress' => isset($profile['achievements'][$key]['completedAt']) ? null
                    : ['current' => $value, 'max' => $max],
            ];
        }
        $journal = $this->journal()->body; $reviews = $this->reviews()->body; $jdata = $journal; $rdata = $reviews;
        $loadout = array_map(fn($a) => (new CatalogService($this->app))->summary($a), $this->addon_rows($id, 8));
        return [
            'user' => $identity, 'stats' => $stats,
            'counts' => [
                'addons' => $addons['count'], 'journal' => $journal['page']['total'],
                'reviews' => $reviews['page']['total'],
            ],
            'loadout' => $loadout, 'addons' => $loadout, 'journal' => $jdata['items'], 'reviews' => $rdata['items'],
            'addonsByExpansion' => (object)array_column($this->db()->all(
                'SELECT v.expansion_id,count(DISTINCT v.addon_id)::int AS count FROM published_versions v '
                    . 'JOIN addons a ON a.id=v.addon_id WHERE a.owner_id=:id GROUP BY v.expansion_id', ['id' => $id]
            ), 'count', 'expansion_id'),
            'titles' => $profile['titles'] ?? [], 'achievements' => $achievements,
            'lastWhisper' => $last ? [
                'text' => $last['body'], 'author' => $this->user_ref($last['author_id']),
                'addonSlug' => $last['slug'], 'createdAt' => $this->date($last['created_at']),
            ] : null,
            'whisper' => $last ? ['text' => $last['body'], 'author' => $this->user_ref($last['author_id'])] : null,
            'heat' => ['from' => $heat[0]['date'], 'counts' => array_column($heat, 'count')],
            'activityHeatmap' => array_map(fn($r) => $r + ['level' => min(3, $r['count'])], $heat),
            'viewer' => $this->user_id() ? [
                'isSelf' => $id === $this->user_id(), 'following' => (bool)$this->db()->value(
                    "SELECT EXISTS(SELECT 1 FROM interactions WHERE kind='follow_user' "
                        . 'AND actor_id=:actor AND target_user_id=:target)',
                    ['actor' => $this->user_id(), 'target' => $id]
                ),
            ] : null,
        ];
    }

    // =========================================================================
    // COMMUNITY SUPPORT AND CONTRIBUTORS
    // =========================================================================

    public function about(): array {
        $support = $this->config('support', [
            'month' => gmdate('Y-m'), 'raised' => 0, 'goal' => 0, 'currency' => 'USD', 'contributors' => 0,
        ]);
        $hall = [];
        $query = 'SELECT owner_id,count(*)::int AS uploads,min(extract(year FROM decided_at))::int AS since ';
        $query .= "FROM submissions WHERE status='approved' GROUP BY owner_id ORDER BY count(*) DESC,owner_id LIMIT 10";
        foreach ($this->db()->all($query) as $row) $hall[] = [
            'user' => $this->user_ref($row['owner_id']), 'uploads' => $row['uploads'], 'since' => $row['since'],
        ];
        return [
            'support' => $support, 'hallOfFame' => $hall,
            'achievements' => $this->config('communityAchievements')['items'] ?? [],
        ];
    }
}
