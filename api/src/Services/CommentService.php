<?php

/**
 * Addons ROG - Addon comments and ratings
 *
 * - List each author's current review with public identity and rating data.
 * - Create or update the viewer's review and return the refreshed aggregate.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{ApiException, Validator as V, Response};

class CommentService extends Service {

    // =========================================================================
    // COMMENT PRESENTATION AND LISTING
    // =========================================================================

    public function present(array $row): array {
        $user = $this->user_ref($row['author_id']);
        $profile = $this->json($this->db()->value('SELECT profile FROM accounts WHERE id=:id', [
            'id' => $row['author_id'],
        ]));
        $user['expansionId'] = $row['expansion_id'] ?? $profile['expansionId'] ?? null;
        if ($user['expansionId'] === null) {
            $query = 'SELECT expansion_id FROM published_versions WHERE addon_id=:id ';
            $query .= 'ORDER BY released_at DESC,id DESC LIMIT 1';
            $user['expansionId'] = $this->db()->value($query, ['id' => $row['addon_id']]) ?: null;
        }
        return [
            'id' => $this->external_id($row['id']), 'addonId' => $this->external_id($row['addon_id']),
            'ownedByViewer' => (int)$row['author_id'] === $this->user_id(), 'user' => $user, 'author' => $user,
            'rating' => $row['rating'] === null ? null : (int)$row['rating'],
            'text' => $row['body'], 'content' => $row['body'], 'expansionId' => $user['expansionId'],
            'createdAt' => $this->date($row['created_at']), 'updatedAt' => $this->date($row['updated_at']),
        ];
    }

    public function listing(): Response {
        $addon = (new CatalogService($this->app))->find($this->request->param('slug'));
        [$limit, $offset] = $this->pagination(10, 50);
        $sort = V::choice($this->request->query['sort'] ?? 'newest', 'sort', ['newest', 'oldest', 'rating']);
        $order = match($sort) {
            'oldest' => 'created_at ASC,id ASC', 'rating' => 'rating DESC NULLS LAST,created_at DESC,id DESC',
            default => 'created_at DESC,id DESC',
        };
        $params = ['id' => $addon['id']]; $where = ' FROM current_ratings WHERE addon_id=:id AND withdrawn_at IS NULL';
        $query = 'SELECT *' . $where . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset';
        $rows = $this->db()->all($query, $params + ['limit' => $limit, 'offset' => $offset]);
        $query = 'SELECT id,rating FROM current_ratings WHERE addon_id=:id AND author_id=:user';
        $mine = $this->db()->one($query, $params + ['user' => $this->user_id()]);
        return $this->page(
            array_map(fn($r) => $this->present($r), $rows),
            (int)$this->db()->value('SELECT count(*)' . $where, $params), $offset, $limit,
            ['viewer' => [
                'commentId' => $mine ? $this->external_id($mine['id']) : null,
                'rating' => $mine ? (int)$mine['rating'] : null,
            ]]
        );
    }

    // =========================================================================
    // VIEWER REVIEW REPLACEMENT
    // =========================================================================

    public function replace(): Response {
        $this->rate('comment:' . $this->user_id(), 20, 3600);
        $addon = (new CatalogService($this->app))->find($this->request->param('slug'));
        $text = V::text($this->request->input('content'), 'content', 1, 4000);
        $rating = $this->request->input('rating'); $rating = V::integer($rating, 'rating', 1, 5);
        $expansion = $this->request->input('expansionId'); $this->expansion($expansion);
        $query = 'SELECT EXISTS(SELECT 1 FROM published_versions WHERE addon_id=:id AND expansion_id=:expansion)';
        if (!$this->db()->value($query, ['id' => $addon['id'], 'expansion' => $expansion])) V::fail('expansionId');

        return $this->db()->transaction(function () use ($addon, $text, $rating, $expansion) {
            // Serialize review replacement for one author/addon pair.
            $this->db()->lock('review:' . $this->user_id() . ':' . $addon['id']);
            $query = 'SELECT id,withdrawn_at FROM comments WHERE addon_id=:addon AND author_id=:user ';
            $query .= 'AND rating IS NOT NULL ORDER BY updated_at DESC,id DESC LIMIT 1 FOR UPDATE';
            $existing = $this->db()->one($query, ['addon' => $addon['id'], 'user' => $this->user_id()]);
            // A withdrawn review cannot be restored through the public editing
            // endpoint.
            if ($existing && $existing['withdrawn_at'] !== null) throw new ApiException(409, 'INVALID_STATE');

            $params = ['text' => $text, 'rating' => $rating, 'expansion' => $expansion];
            if ($existing) {
                $query = 'UPDATE comments SET body=:text,rating=:rating,expansion_id=:expansion,';
                $query .= 'updated_at=clock_timestamp(),withdrawn_at=NULL WHERE id=:id RETURNING *';
                $row = $this->row($query, $params + ['id' => $existing['id']]);
            } else {
                $query = 'INSERT INTO comments(addon_id,author_id,body,rating,expansion_id) ';
                $query .= 'VALUES (:addon,:user,:text,:rating,:expansion) RETURNING *';
                $row = $this->row($query, $params + ['addon' => $addon['id'], 'user' => $this->user_id()]);
            }
            $this->audit('comment', 'comment', $row['id'], [
                'targetLabel' => $addon['name'], 'addonSlug' => $addon['slug'], 'rating' => $rating,
            ]);
            // Read the current ratings view after the review mutation so the
            // response reflects the saved value.
            $aggregate = $this->db()->one('SELECT * FROM addon_ratings WHERE addon_id=:id', ['id' => $addon['id']]);
            $value = $aggregate ? (float)$aggregate['rating'] : null; $votes = (int)($aggregate['votes'] ?? 0);
            return Response::ok($this->request, [
                'comment' => $this->present($row), 'addonRating' => ['rating' => $value, 'votes' => $votes],
                'rating' => $value, 'ratingCount' => $votes,
            ], $existing ? 200 : 201);
        });
    }
}
