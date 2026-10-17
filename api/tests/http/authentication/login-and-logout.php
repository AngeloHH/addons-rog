<?php

/**
 * Addons ROG - Login and logout tests
 *
 * - Check repeated logout, session responses, and remembered password login.
 */

$other->call('POST', '/api/auth/logout'); $other->call('GET', '/api/auth/session');
$other->call('POST', '/api/auth/logout'); $other->call('GET', '/api/auth/session');
$other->call(
    'POST', '/api/auth/login', ['identifier' => 'OtherUser', 'password' => 'A secure password 123', 'remember' => true]
);
