<?php

/**
 * Addons ROG - Review creation and editing tests
 *
 * - Create author reviews, update an existing review, and check current rating counts.
 */

$author->call(
    'PUT', '/api/addons/' . $slug . '/my-comment', ['content' => 'Works well', 'rating' => 4, 'expansionId' => 'wotlk'],
    201
);
$review = $other->call(
    'PUT', '/api/addons/' . $slug . '/my-comment', ['content' => 'Excellent', 'rating' => 5, 'expansionId' => 'wotlk'],
    201
);
$other->call(
    'PUT', '/api/addons/' . $slug . '/my-comment',
    ['content' => 'Still excellent', 'rating' => 5, 'expansionId' => 'wotlk']
);
check(
    (int)$db->value('SELECT count(*) FROM current_ratings WHERE addon_id=:id', ['id' => $aid]) === 2,
    'One current rating per user'
);
