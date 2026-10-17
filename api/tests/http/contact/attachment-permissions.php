<?php

/**
 * Addons ROG - Contact attachment access tests
 *
 * - Persist a valid image attachment and restrict retrieval to staff.
 */

$contact = $author->call(
    'POST', '/api/contact',
    [
        'recipientId' => 'account:' . $founder_identity['id'], 'topicId' => 'other',
        'name' => 'Author', 'subject' => 'Attachment example',
        'message' => 'This message includes a valid image attachment.'
    ],
    201, ['field' => 'attachment', 'name' => 'example.png', 'type' => 'image/png', 'bytes' => png_fixture(32, 32)]
);
$attached = $db->one(
    'SELECT f.* FROM assets f JOIN asset_links l ON l.asset_id=f.id WHERE l.message_id=:id',
    ['id' => $contact['ticketId']]
);
check($attached !== null, 'Contact attachment persisted');
// Knowing the attachment UUID must not grant public access.
$guest->call('GET', '/api/files/' . $attached['external_id'], null, 404);
$founder->call('GET', '/api/files/' . $attached['external_id']);
