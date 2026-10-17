<?php

/**
 * Addons ROG - Fulfilled request interaction tests
 *
 * - Reject vote changes after fulfillment while allowing the viewer to follow updates.
 */

$author->call('PUT', '/api/requests/' . $rid . '/vote', ['voted' => true], 409);
$author->call('PUT', '/api/requests/' . $rid . '/follow', ['following' => true]);
$fulfilled = $author->call('GET', '/api/requests?state=fulfilled')['items'];
check(in_array($rid, array_column($fulfilled, 'id'), true), 'Linked published addon fulfills its request');
