<?php

/**
 * Addons ROG - Guest permission tests
 *
 * - Check public health access, protected administration, and registration CSRF requirements.
 */

$guest = new Client(); $guest->call('GET', '/api/health');
$guest->call('GET', '/api/admin/counts', null, 401); $guest->call('POST', '/api/auth/register', [], 403);
