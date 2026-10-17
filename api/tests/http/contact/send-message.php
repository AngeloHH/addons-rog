<?php

/**
 * Addons ROG - Contact submission tests
 *
 * - Submit a valid contact message through the public ticket endpoint.
 */

$contact = $other->call(
    'POST', '/api/contact',
    [
        'recipientId' => 'account:' . $founder_identity['id'], 'topicId' => 'other',
        'name' => 'Other', 'subject' => 'A contact test',
        'message' => 'This is a contact message for integration tests.'
    ],
    201
);
$ticket = $db->one('SELECT recipient_id FROM messages WHERE id=:id', ['id' => $contact['ticketId']]);
check((int)$ticket['recipient_id'] === (int)$founder_identity['id'], 'Contact ticket links the actual founder account');
$notification = $db->one("SELECT * FROM messages WHERE kind='notification' AND parent_id=:id", [
    'id' => $contact['ticketId'],
]);
$mail = (new Rog\Services\NotificationService(test_app()))->decode(json_decode($notification['data'], true));
check($mail['to'] === 'founder@example.test', 'Founder notification uses the account email');
