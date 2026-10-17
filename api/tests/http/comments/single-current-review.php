<?php

/**
 * Addons ROG - Single current review tests
 *
 * - Verify stable review IDs, required rating fields, and the current public review.
 */

// =============================================================================
// STABLE REVIEW ID AND ROW COUNT
// =============================================================================

$before = (int)$db->value(
    'SELECT count(*) FROM comments WHERE addon_id=:addon AND author_id=:user', ['addon' => $aid, 'user' => $author_id]
);
$updated = $author->call(
    'PUT', '/api/addons/' . $slug . '/my-comment',
    ['content' => 'Updated single review', 'rating' => 3, 'expansionId' => 'wotlk']
);
$same = $author->call(
    'PUT', '/api/addons/' . $slug . '/my-comment',
    ['content' => 'Updated single review', 'rating' => 3, 'expansionId' => 'wotlk']
);
check($updated['comment']['id'] === $same['comment']['id'], 'Updating a review preserves its ID');
check(
    $before === (int)$db->value(
        'SELECT count(*) FROM comments WHERE addon_id=:addon AND author_id=:user',
        ['addon' => $aid, 'user' => $author_id]
    ),
    'Updating a review does not create another comment'
);
$author->call(
    'PUT', '/api/addons/' . $slug . '/my-comment', ['content' => 'Missing rating', 'expansionId' => 'wotlk'], 422
);
$author->call(
    'PUT', '/api/addons/' . $slug . '/my-comment',
    ['content' => 'Unavailable expansion', 'rating' => 3, 'expansionId' => 'df'], 422
);

// =============================================================================
// CURRENT PUBLIC REVIEW
// =============================================================================

$list = $guest->call('GET', '/api/addons/' . $slug . '/comments');
$mine = array_values(array_filter($list['items'], fn($r) => $r['user']['id'] === $author_id));
check(
    count($mine) === 1 && $mine[0]['rating'] === 3 && $mine[0]['content'] === 'Updated single review',
    'Public list contains the current review only'
);
