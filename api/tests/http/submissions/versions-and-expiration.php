<?php

/**
 * Addons ROG - Version uniqueness and draft expiry tests
 *
 * - Publish an update, reject duplicate/incomplete versions, and reject an expired draft.
 */

// =============================================================================
// PUBLISHED VERSION AND DUPLICATE REJECTION
// =============================================================================

$version_upload = $author->call(
    'POST', '/api/uploads', [], 201, ['name' => 'version.zip', 'type' => 'application/zip', 'bytes' => $bytes]
)['upload'];
$version = $author->call(
    'POST', '/api/submissions',
    [
        'addonSlug' => $slug, 'uploadId' => $version_upload['id'], 'version' => '2.0', 'expansionId' => 'wotlk',
        'rulesAccepted' => true, 'testedConfirmed' => true
    ],
    201
)['submission'];
$author->call('POST', '/api/submissions/' . $version['id'] . '/submit', []);
$founder->call('POST', '/api/admin/submissions/' . $version['id'] . '/decision', ['decision' => 'approve']);
$duplicate = $author->call(
    'POST', '/api/submissions',
    [
        'addonSlug' => $slug, 'uploadId' => $version_upload['id'], 'version' => '2.0', 'expansionId' => 'wotlk',
        'rulesAccepted' => true, 'testedConfirmed' => true
    ],
    201
)['submission'];
$author->call('POST', '/api/submissions/' . $duplicate['id'] . '/submit', [], 409);

// =============================================================================
// INCOMPLETE AND EXPIRED DRAFTS
// =============================================================================

$empty = $other->call('POST', '/api/submissions', [], 201)['submission'];
$other->call('POST', '/api/submissions/' . $empty['id'] . '/submit', [], 422);
$db->query("UPDATE submissions SET updated_at=now()-interval '31 days' WHERE id=:id", ['id' => $empty['id']]);
// The immutable/update trigger uses the current clock; expiry fixture is inserted directly.
$expired = $db->value(
    "INSERT INTO submissions(owner_id,kind,updated_at) VALUES " . "(:id,'addon',now()-interval '31 days') RETURNING id",
    ['id' => $other_id]
);
$other->call('GET', '/api/submissions/' . $expired, null, 410);
