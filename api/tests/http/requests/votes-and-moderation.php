<?php

/**
 * Addons ROG - Request voting and moderation tests
 *
 * - Verify idempotent votes, author notification limits, and closed-request voting rules.
 */

$r = $other->call(
    'POST', '/api/requests',
    ['addonName' => 'Open request', 'expansionId' => 'wotlk', 'text' => 'A request to test votes'], 201
)['request'];
$v1 = $other->call('PUT', '/api/requests/' . $r['id'] . '/vote', ['voted' => true]);
$v2 = $other->call('PUT', '/api/requests/' . $r['id'] . '/vote', ['voted' => true]);
check($v1['voteCount'] === $v2['voteCount'], 'Repeated desired vote is idempotent');
$other->call('PUT', '/api/requests/' . $r['id'] . '/follow', ['following' => true]);
$request = $other->call('GET', '/api/requests?q=Open%20request')['items'][0];
check($request['viewer']['voted'] && $request['viewer']['following'], 'Request vote and follow survive a fresh read');
$founder->call('POST', '/api/admin/requests/' . $r['id'] . '/action', ['action' => 'ping']);
$founder->call('POST', '/api/admin/requests/' . $r['id'] . '/action', ['action' => 'ping'], 429);
$founder->call('POST', '/api/admin/requests/' . $r['id'] . '/action', ['action' => 'close']);
$closed = $other->call('GET', '/api/requests?state=closed')['items'];
check(in_array($r['id'], array_column($closed, 'id'), true), 'Closed request appears in the closed listing');
$other->call('PUT', '/api/requests/' . $r['id'] . '/vote', ['voted' => false], 409);
