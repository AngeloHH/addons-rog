<?php

/**
 * Addons ROG - Shared moderation contact tests
 *
 * - Send one ticket to every eligible moderator and founder with separate notifications.
 * - Keep ordinary, unverified, and banned accounts outside the delivery group.
 */

// =============================================================================
// TWO FOUNDERS, TWO MODERATORS, AND INELIGIBLE ACCOUNTS
// =============================================================================

$founder->call('PATCH', '/api/admin/accounts/' . $author_id, ['role' => 'founder']);
$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['role' => 'moderator']);
$accounts = new Rog\Models\Account($db);
$hash = $db->value('SELECT password_hash FROM accounts WHERE id=:id', ['id' => $founder_identity['id']]);
$extraStaff = [];
foreach (['GroupModerator', 'UnverifiedModerator', 'BannedFounder'] as $username) {
    $extraStaff[] = $accounts->create([
        'username' => $username, 'email' => strtolower($username) . '@example.test', 'password_hash' => $hash,
        'verified_at' => $username === 'UnverifiedModerator' ? null : gmdate('c'),
        'banned_at' => $username === 'BannedFounder' ? gmdate('c') : null,
        'access_level' => $username === 'BannedFounder' ? 2 : 1,
    ]);
}

// =============================================================================
// ONE TICKET, ONE ATTACHMENT, AND ONE NOTIFICATION PER MEMBER
// =============================================================================

$clearContactLimit();
$contact = $other->call('POST', '/api/contact', [
    'recipientId' => 'group:moderation', 'topicId' => 'other', 'name' => 'Other',
    'subject' => 'Message for the whole moderation team',
    'message' => 'Both founders and every verified moderator must receive this ticket.',
], 201, ['field' => 'attachment', 'name' => 'team.png', 'type' => 'image/png', 'bytes' => png_fixture(32, 32)]);
$ticket = $db->one('SELECT * FROM messages WHERE id=:id', ['id' => $contact['ticketId']]);
check($ticket['recipient_id'] === null, 'Shared contact ticket has no single account recipient');
$notifications = $db->all("SELECT * FROM messages WHERE kind='notification' AND parent_id=:id", [
    'id' => $contact['ticketId'],
]);
$expected = [(int)$founder_identity['id'], $author_id, $other_id, (int)$extraStaff[0]->id];
$actual = array_map('intval', array_column($notifications, 'recipient_id'));
sort($expected); sort($actual);
check($actual === $expected, 'Every eligible founder and moderator receives exactly one notification');
$addresses = []; $keys = []; $mailer = new Rog\Services\NotificationService(test_app());
foreach ($notifications as $notification) {
    $data = json_decode($notification['data'], true); $mail = $mailer->decode($data);
    $addresses[] = $mail['to']; $keys[] = $data['dedupeKey'];
    check(str_contains($mail['body'], '/api/files/'), 'Each team member receives the attachment link');
}
sort($addresses);
check($addresses === [
    'authorone@example.test', 'founder@example.test', 'groupmoderator@example.test', 'otheruser@example.test',
], 'Group notifications use only the actual eligible account addresses');
check(count(array_unique($keys)) === 4, 'Queue deduplication keeps all four group notifications');
$attached = $db->one('SELECT a.* FROM assets a JOIN asset_links l ON l.asset_id=a.id WHERE l.message_id=:id', [
    'id' => $contact['ticketId'],
]);
$guest->call('GET', '/api/files/' . $attached['external_id'], null, 404);
$other->call('GET', '/api/files/' . $attached['external_id']);
$founder->call('GET', '/api/files/' . $attached['external_id']);

// Restore shared identities while keeping the queued delivery evidence intact.
$founder->call('PATCH', '/api/admin/accounts/' . $author_id, ['role' => 'player']);
$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['role' => 'player']);
foreach ($extraStaff as $account) $db->query('UPDATE accounts SET access_level=0 WHERE id=:id', ['id' => $account->id]);
$clearContactLimit();
