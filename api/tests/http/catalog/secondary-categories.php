<?php

/**
 * Addons ROG - Published secondary category tests
 *
 * - Find one published addon through every selected category and update each category count.
 */

// =============================================================================
// PUBLICATION WITH MORE THAN ONE CATEGORY
// =============================================================================

$categoryGroup = function (array $taxonomy): array {
    foreach ($taxonomy['groups'] as $group) if ($group['id'] === 'category')
        return array_column($group['items'], 'addonCount', 'id');
    throw new RuntimeException('Missing category group');
};
$countsBefore = $categoryGroup($guest->call('GET', '/api/taxonomy'));
$author->call('POST', '/api/submissions/' . $categorizedDraft['id'] . '/submit', []);
$categorizedAddon = $founder->call(
    'POST', '/api/admin/submissions/' . $categorizedDraft['id'] . '/decision', ['decision' => 'approve']
)['addon'];

// =============================================================================
// SECONDARY FILTERS AND CATEGORY TOTALS
// =============================================================================

$countsAfter = $categoryGroup($guest->call('GET', '/api/taxonomy'));
foreach (['pve', 'map'] as $category) {
    $matches = $guest->call('GET', '/api/addons?expansion=wotlk&category=' . $category);
    check(
        in_array($categorizedAddon['slug'], array_column($matches['items'], 'slug'), true),
        'Published addon is discoverable in category ' . $category
    );
    check($countsAfter[$category] === $countsBefore[$category] + 1, 'Category total includes addon: ' . $category);
}
