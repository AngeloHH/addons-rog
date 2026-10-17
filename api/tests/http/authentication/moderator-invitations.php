<?php

/**
 * Addons ROG - Moderator invitation tests
 *
 * - Accept an emailed invitation and verify the resulting moderator access limits.
 */

$founder->call('POST', '/api/admin/invitations', ['email' => 'authorone@example.test', 'role' => 'moderator'], 201);
$invitation = $db->one(
    "SELECT m.data FROM messages m JOIN auth_tokens t ON " . "m.data->>'dedupeKey'='invite:'||t.id::text WHERE "
        . "t.email='authorone@example.test' ORDER BY m.id DESC LIMIT 1"
);
// Read the encrypted test queue directly instead of sending external email.
$mail = (new Rog\Services\NotificationService(test_app()))->decode(json_decode($invitation['data'], true));
preg_match('/invitationToken=([a-f0-9]{64})/', $mail['body'], $match);
$author->call('POST', '/api/auth/invitations/accept', ['invitationToken' => $match[1]]);
$author->call('GET', '/api/admin/counts');
$author->call('PATCH', '/api/admin/accounts/' . $other_id, ['status' => 'banned'], 403);
$author->call('POST', '/api/admin/invitations', ['email' => 'no@example.test', 'role' => 'moderator'], 403);
