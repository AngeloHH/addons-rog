<?php

/**
 * Addons ROG - Canonical API route tests
 *
 * - Match the route inventory and reject removed aliases and unsupported actions.
 */

// =============================================================================
// REGISTERED ROUTE INVENTORY
// =============================================================================

$routes = json_decode(file_get_contents($testRoot . '/fixtures/routes.json'), true, 512, JSON_THROW_ON_ERROR);
$expected = array_map(fn($e) => $e['method'] . ' ' . $e['url'], $routes);
$implemented = array_map(fn($r) => $r[0] . ' /api' . $r[1], require $apiRoot . '/routes.php');
sort($expected);
sort($implemented);
check($expected === $implemented, 'Registered routes match the expected route inventory');
check(count(array_unique($implemented)) === count($implemented), 'No duplicate route registrations');

// =============================================================================
// REMOVED ROUTE ALIASES
// =============================================================================

foreach (
    [
        ['GET', '/auth/me'], ['POST', '/auth/resend-verification'], ['GET', '/users/AuthorOne'],
        ['GET', '/contact/context'], ['POST', '/contact/messages'], ['POST', '/upload-files'],
        ['POST', '/upload-drafts'], ['GET', '/upload-drafts/1'], ['PATCH', '/upload-drafts/1'],
        ['POST', '/upload-drafts/1/submit'], ['GET', '/addon-versions/1/download'], ['GET', '/admin/review-queue'],
        ['GET', '/admin/review-queue/1/zip'], ['POST', '/admin/review-queue/1/decision'],
        ['POST', '/admin/moderator-invitations'], ['POST', '/admin/accounts/1/messages'],
        ['PUT', '/admin/reports/1/resolution'], ['PATCH', '/admin/requests/1'], ['GET', '/expansions'],
        ['GET', '/upload-options'], ['GET', '/auth/session.php'],
    ] as [$method, $path]
) $guest->call($method, '/api' . $path, null, 404);
$guest->call('POST', '/api/addons/' . $slug . '/comments', [], 405);
$guest->call('PUT', '/api/addons/' . $slug . '/my-comment', [], 401);
