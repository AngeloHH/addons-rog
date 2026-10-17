<?php

/**
 * Addons ROG - Submission rejection tests
 *
 * - Submit another author's addon and reject it with a moderation rule and reason.
 */

$second_upload = $other->call(
    'POST', '/api/uploads', [], 201, ['name' => 'addon.zip', 'type' => 'application/zip', 'bytes' => $bytes]
)['upload'];
$second_draft = $other->call(
    'POST', '/api/submissions',
    [
        'name' => 'Second Addon', 'version' => '1.1', 'categoryId' => 'map', 'expansionId' => 'wotlk',
        'summary' => 'Second addon', 'uploadId' => $second_upload['id'], 'rulesAccepted' => true,
        'testedConfirmed' => true
    ],
    201
)['submission'];
$gid = $second_draft['id']; $other->call('GET', '/api/submissions/' . $gid);
$second_draft = $other->call(
    'PATCH', '/api/submissions/' . $gid, ['revision' => $second_draft['revision'], 'description' => 'Description']
)['submission'];
$other->call('PATCH', '/api/submissions/' . $gid, ['revision' => 999, 'name' => 'stale'], 409);
$other->call('POST', '/api/submissions/' . $gid . '/submit', ['revision' => $second_draft['revision']]);
$founder->call('GET', '/api/admin/submissions'); $founder->call('GET', '/api/admin/submissions/' . $gid . '/file');
$founder->call(
    'POST', '/api/admin/submissions/' . $gid . '/decision',
    ['decision' => 'reject', 'ruleId' => 'credit', 'reason' => 'Missing author credits']
);
