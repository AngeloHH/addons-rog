<?php

/**
 * Addons ROG - Account ban tests
 *
 * - Verify that banning an account invalidates its authenticated session.
 */

$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['status' => 'banned']);
$other->call('GET', '/api/submissions', null, 401);
