<?php

/**
 * Addons ROG - Addon publication lifecycle tests
 *
 * - Upload a real archive, edit its draft, link a request, and approve publication.
 */

// =============================================================================
// REAL ARCHIVE UPLOAD
// =============================================================================

$tmp = tempnam(sys_get_temp_dir(), 'rog'); $zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);
$zip->addFromString('TestAddon/TestAddon.toc', "## Interface: 30300\n## Title: TestAddon\nmain.lua\n");
$zip->addFromString('TestAddon/main.lua', 'local version = 1'); $zip->close();
$bytes = file_get_contents($tmp);
unlink($tmp);
$upload = $author->call(
    'POST', '/api/uploads', [], 201, ['name' => 'addon.zip', 'type' => 'application/zip', 'bytes' => $bytes]
)['upload'];
check($upload['status'] === 'clean', 'ZIP scan clean');
$draft = $author->call(
    'POST', '/api/submissions',
    [
        'uploadId' => $upload['id'], 'name' => 'Test Addon', 'version' => '1.0', 'categoryId' => 'pve',
        'expansionId' => 'wotlk', 'summary' => 'Integration addon', 'description' => 'Test description',
        'rulesAccepted' => true, 'testedConfirmed' => true
    ],
    201
)['submission'];

// =============================================================================
// DRAFT OWNERSHIP AND REVISIONS
// =============================================================================

$sid = $draft['id']; $other->call('GET', '/api/submissions/' . $sid, null, 404);
$author->call('PATCH', '/api/submissions/' . $sid, ['revision' => 999, 'summary' => 'stale'], 409);
$draft = $author->call(
    'PATCH', '/api/submissions/' . $sid, ['revision' => $draft['revision'], 'summary' => 'Updated summary']
)['submission'];

// =============================================================================
// REQUEST LINK AND REVIEW
// =============================================================================

$request = $author->call(
    'POST', '/api/requests',
    ['addonName' => 'Test Addon', 'expansionId' => 'wotlk', 'text' => 'Please publish this addon'], 201
)['request'];
$rid = $request['id'];
$draft = $author->call(
    'PATCH', '/api/submissions/' . $sid, ['requestId' => $rid, 'revision' => $draft['revision']]
)['submission'];
$author->call('POST', '/api/submissions/' . $sid . '/submit', ['revision' => $draft['revision']]);
check(
    $author->call('GET', '/api/submissions/' . $sid)['submission']['status'] === 'pending', 'Submitted draft is pending'
);
$author->call('PATCH', '/api/submissions/' . $sid, ['summary' => 'cannot edit'], 409);
$founder->call('GET', '/api/admin/submissions'); $founder->call('GET', '/api/admin/submissions/' . $sid);
$founder->call('GET', '/api/admin/submissions/' . $sid . '/file');
$approved = $founder->call('POST', '/api/admin/submissions/' . $sid . '/decision', ['decision' => 'approve']);
$slug = $approved['addon']['slug']; $aid = $approved['addon']['id'];
$founder->call('POST', '/api/admin/submissions/' . $sid . '/decision', ['decision' => 'approve'], 409);
