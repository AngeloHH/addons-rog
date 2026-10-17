<?php

/**
 * Addons ROG - Taxonomy and upload limit tests
 *
 * - Verify the complete expansion/group inventory and supported upload limits.
 */

$taxonomy = $guest->call('GET', '/api/taxonomy');
check(
    count($taxonomy['expansions']) === 7
        && array_column($taxonomy['groups'], 'id') === ['category', 'classes', 'professions', 'role'],
    'Taxonomy provides all catalogue choices'
);
check(
    $taxonomy['limits'] === [
        'summaryMax' => 120, 'screenshotsMax' => 4, 'addonZipMaxBytes' => 52428800, 'imageMaxBytes' => 2097152
    ],
    'Taxonomy retains upload limits'
);
