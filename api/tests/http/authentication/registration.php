<?php

/**
 * Addons ROG - Registration and role fixtures
 *
 * - Create verified author, player, and founder identities for subsequent scenarios.
 */

$author = new Client(); $identity = register($author, 'AuthorOne', true);
check(
    $author->call('GET', '/api/auth/session')['user']['name'] === 'AuthorOne',
    'Verification creates the user session'
);
$author_id = (int)$identity['id']; $other = new Client();
$other_identity = register($other, 'OtherUser'); $other_id = (int)$other_identity['id'];
$founder = new Client(); $founder_identity = register($founder, 'Founder');
// Promote only the disposable founder fixture, then reload its session
// permissions.
$db->query('UPDATE accounts SET access_level=2 WHERE id=:id', ['id' => $founder_identity['id']]);
$founder->call('GET', '/api/auth/session'); $author->call('GET', '/api/admin/counts', null, 403);
