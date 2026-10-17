<?php

/**
 * Addons ROG - Public catalog endpoint tests
 *
 * - Exercise published catalog pages, filtered listings, name checks, and favorites.
 */

$db->query('REFRESH MATERIALIZED VIEW catalog_metrics');
foreach (
    [
        '/api/taxonomy', '/api/site/stats', '/api/home', '/api/addons',
        '/api/addons?expansion=wotlk&category=pve&sort=rating', '/api/addons/' . $slug,
        '/api/addons/' . $slug . '/comments', '/api/requests', '/api/contact', '/api/profiles/AuthorOne',
        '/api/users/AuthorOne/addons', '/api/users/AuthorOne/journal', '/api/users/AuthorOne/reviews', '/api/about'
    ] as $path
) $guest->call('GET', $path);
$author->call('GET', '/api/addons/check-name?name=Test%20Addon&expansion=wotlk&version=1.0');
$author->call('PUT', '/api/addons/' . $slug . '/favorite', ['favorited' => true]);
check($author->call('GET', '/api/addons/' . $slug)['addon']['viewer']['isFavorite'], 'Favorite survives a fresh read');
