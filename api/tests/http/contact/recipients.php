<?php

/**
 * Addons ROG - Contact recipient discovery tests
 *
 * - Discover real staff members and explicitly configured mailboxes without leaking addresses.
 * - Follow role changes and reject invented, inactive, or removed destinations.
 */

// =============================================================================
// REAL ACCOUNTS AND CURRENT STAFF PERMISSIONS
// =============================================================================

$contactContext = $guest->call('GET', '/api/contact');
$founderContactId = 'account:' . $founder_identity['id'];
check(count($contactContext['recipients']) === 2, 'Founder and moderation group appear without mailbox settings');
$recipient = $contactContext['recipients'][0];
check($recipient['id'] === $founderContactId && $recipient['name'] === 'Founder', 'Founder comes from account data');
check($recipient['kind'] === 'founder', 'Founder contact kind');
$group = $contactContext['recipients'][1];
check($group['id'] === 'group:moderation' && $group['kind'] === 'moderation', 'Shared moderation card');
check($group['name'] === null, 'Moderation card name is localized by the frontend');
check(!str_contains(json_encode($contactContext), '@example.test'), 'Recipient email addresses remain private');

$contactBody = [
    'topicId' => 'other', 'name' => 'Other', 'subject' => 'Dynamic contact recipient',
    'message' => 'This contact message must reach a real configured destination.',
];
$clearContactLimit = function () use ($db, $config) {
    $key = hash_hmac('sha256', 'contact:127.0.0.1', $config['secret']);
    $db->query("DELETE FROM events WHERE kind='rateLimit' AND data->>'key'=:key", ['key' => $key]);
};
$clearContactLimit();
$before = (int)$db->value("SELECT count(*) FROM messages WHERE kind='contact'");
$other->call('POST', '/api/contact', $contactBody + ['recipientId' => 'angelo'], 422);
$other->call('POST', '/api/contact', $contactBody + ['recipientId' => 'account:' . $author_id], 422);
$after = (int)$db->value("SELECT count(*) FROM messages WHERE kind='contact'");
check($after === $before, 'Invalid recipients create no ticket');

$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['role' => 'founder']);
$ids = array_column($guest->call('GET', '/api/contact')['recipients'], 'id');
check(in_array('account:' . $other_id, $ids, true), 'New founder appears without a fixed name');
$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['role' => 'moderator']);
$recipients = $guest->call('GET', '/api/contact')['recipients'];
check(count($recipients) === 2, 'Moderators share one group card instead of individual cards');
$ids = array_column($recipients, 'id');
check(!in_array('account:' . $other_id, $ids, true), 'Moderator has no individual founder card');
check(in_array('group:moderation', $ids, true), 'Moderation group remains available after a role change');
$clearContactLimit();
$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['role' => 'player']);
$ids = array_column($guest->call('GET', '/api/contact')['recipients'], 'id');
check(!in_array('account:' . $other_id, $ids, true), 'Account without staff permissions disappears');
$other->call('POST', '/api/contact', $contactBody + ['recipientId' => 'account:' . $other_id], 422);
$clearContactLimit();

// =============================================================================
// EXPLICIT MAILBOXES AND DELIVERY TARGETS
// =============================================================================

$mailboxes = [
    'support' => ['name' => 'Support desk', 'email' => 'support@example.test', 'replyHours' => 12],
    'missing' => ['name' => 'No address'], 'invalid' => ['email' => 'not-an-email'],
];
$db->query('INSERT INTO settings(key,value) VALUES (:key,CAST(:value AS jsonb))', [
    'key' => 'contactRecipients', 'value' => json_encode($mailboxes),
]);
$recipients = $guest->call('GET', '/api/contact')['recipients'];
check(count($recipients) === 3, 'Only configured mailboxes with valid email are added');
$mailbox = $recipients[2];
check($mailbox['id'] === 'mailbox:support' && $mailbox['name'] === 'Support desk', 'Configured mailbox identity');
check($mailbox['kind'] === 'mailbox' && $mailbox['username'] === null, 'Mailbox does not impersonate an account');
$contact = $other->call('POST', '/api/contact', $contactBody + ['recipientId' => $mailbox['id']], 201);
check($contact['estimatedReplyHours'] === 12, 'Configured mailbox reply estimate');
$notification = $db->one("SELECT * FROM messages WHERE kind='notification' AND parent_id=:id", [
    'id' => $contact['ticketId'],
]);
$mail = (new Rog\Services\NotificationService(test_app()))->decode(json_decode($notification['data'], true));
check($mail['to'] === 'support@example.test', 'Notification uses the selected mailbox address');
$recipients = $guest->call('GET', '/api/contact')['recipients'];
check($recipients[2]['load'] === 5, 'Recipient workload counts its persisted tickets');
$db->query("DELETE FROM settings WHERE key='contactRecipients'");
$other->call('POST', '/api/contact', $contactBody + ['recipientId' => $mailbox['id']], 422);
$clearContactLimit();
