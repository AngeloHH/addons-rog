<?php

/**
 * Addons ROG - Archive validation tests
 *
 * - Reject unsafe paths and prevent blocked executable archives from entering review.
 */

$bad = archive_fixture(['../escape.lua' => 'bad']);
$author->call('POST', '/api/uploads', [], 422, ['name' => 'bad.zip', 'type' => 'application/zip', 'bytes' => $bad]);
$danger = archive_fixture(['Danger/Danger.toc' => "## Interface: 30300\n", 'Danger/evil.exe' => 'MZ executable']);
$blocked = $author->call(
    'POST', '/api/uploads', [], 201, ['name' => 'danger.zip', 'type' => 'application/zip', 'bytes' => $danger]
)['upload'];
check($blocked['status'] === 'blocked', 'Executable upload quarantined');
$blocked_draft = $author->call(
    'POST', '/api/submissions',
    [
        'uploadId' => $blocked['id'], 'name' => 'Danger', 'version' => '1.0', 'expansionId' => 'wotlk',
        'categoryId' => 'pve', 'summary' => 'Blocked addon', 'rulesAccepted' => true, 'testedConfirmed' => true
    ],
    201
)['submission'];
$author->call('POST', '/api/submissions/' . $blocked_draft['id'] . '/submit', [], 422);
