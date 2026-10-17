<?php

/**
 * Addons ROG - Draft taxonomy and screenshot persistence tests
 *
 * - Reload selected categories, classes, professions, and four screenshots through the API.
 * - Keep the saved draft intact when a fifth screenshot is rejected.
 */

// =============================================================================
// OWNED ARCHIVE AND SCREENSHOT FIXTURES
// =============================================================================

$categorizedUpload = $author->call('POST', '/api/uploads', [], 201, [
    'name' => 'categorized.zip', 'type' => 'application/zip', 'bytes' => $bytes,
])['upload'];
$screenshotIds = [];
for ($index = 0; $index < 5; $index++) $screenshotIds[] = $author->call(
    'POST', '/api/media', ['kind' => 'screenshot'], 201,
    ['name' => 'screen-' . $index . '.png', 'type' => 'image/png', 'bytes' => png_fixture(640, 360)]
)['media']['id'];
$categorizedDraft = $author->call('POST', '/api/submissions', [
    'uploadId' => $categorizedUpload['id'], 'name' => 'Categorized Addon', 'version' => '1.0',
    'expansionId' => 'wotlk', 'categoryIds' => ['pve', 'map'], 'classIds' => ['mage'],
    'professionIds' => ['alchemy'], 'summary' => 'An addon with multiple categories.',
    'description' => 'Draft fields remain available after reopening.',
    'screenshotMediaIds' => array_slice($screenshotIds, 0, 4), 'rulesAccepted' => true, 'testedConfirmed' => true,
], 201)['submission'];

// =============================================================================
// REOPENED DRAFT AND SCREENSHOT LIMIT
// =============================================================================

$draftPath = '/api/submissions/' . $categorizedDraft['id'];
$reloadedDraft = $author->call('GET', $draftPath)['submission'];
check($reloadedDraft['categoryIds'] === ['pve', 'map'], 'Draft retains all selected categories in order');
check($reloadedDraft['classIds'] === ['mage'], 'Draft retains selected classes');
check($reloadedDraft['professionIds'] === ['alchemy'], 'Draft retains selected professions');
check(
    array_column($reloadedDraft['screenshots'], 'id') === array_slice($screenshotIds, 0, 4),
    'Draft retains all four screenshots in order'
);
$author->call('PATCH', $draftPath, ['screenshotMediaIds' => $screenshotIds], 422);
check(
    $author->call('GET', $draftPath)['submission']['screenshots'] === $reloadedDraft['screenshots'],
    'Rejected fifth screenshot leaves the saved attachments intact'
);
