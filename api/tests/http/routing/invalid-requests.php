<?php

/**
 * Addons ROG - Invalid request handling tests
 *
 * - Check method errors, invalid catalog filters, and protected activity exports.
 */

$guest->call('GET', '/api/health'); $guest->call('GET', '/api/default-icon.svg');
$guest->call('DELETE', '/api/health', null, 405); $guest->call('GET', '/api/addons?category=unknown', null, 422);
$guest->call('GET', '/api/admin/activity/export', null, 401);
