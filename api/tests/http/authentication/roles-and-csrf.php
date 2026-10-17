<?php

/**
 * Addons ROG - Role and CSRF rejection tests
 *
 * - Reject unauthorized role changes, self-demotion, and an invalid CSRF token.
 */

$author->call('PATCH', '/api/admin/accounts/' . $other_id, ['role' => 'founder'], 403);
$founder->call('PATCH', '/api/admin/accounts/' . $founder_identity['id'], ['accessLevel' => 0], 403);
// Restore the valid token after the rejection so later scenarios keep their
// authenticated client.
$saved_csrf = $author->csrf; $author->csrf = 'wrong';
$author->call('PUT', '/api/addons/' . $slug . '/favorite', ['favorited' => false], 403);
$author->csrf = $saved_csrf;
