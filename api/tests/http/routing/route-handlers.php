<?php

/**
 * Addons ROG - Registered route handler tests
 *
 * - Resolve each inventoried route and require a callable service action.
 */

$routes = require $apiRoot . '/routes.php'; $router = new Rog\Http\Router($routes);
foreach (json_decode(file_get_contents($testRoot . '/fixtures/routes.json'), true) as $entry) {
    // Substitute a sample path segment so parameterized routes can be matched
    // before content fixtures.
    $path = preg_replace('/\{[^}]+\}/', '1', $entry['url']);
    $match = $router->match(new Rog\Http\Request($entry['method'], $path));
    check(
        is_callable([new $match['class'](test_app()), $match['action']]),
        'Route ' . $entry['method'] . ' ' . $entry['url']
    );
}
