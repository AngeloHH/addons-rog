<?php

/**
 * Addons ROG - Administration endpoint tests
 *
 * - Check that founder access reaches each dashboard, queue, and export endpoint.
 */

foreach (
    [
        '/api/admin/counts', '/api/admin/dashboard', '/api/admin/submissions', '/api/admin/reports',
        '/api/admin/accounts', '/api/admin/activity', '/api/admin/activity/export', '/api/admin/stats',
        '/api/admin/live'
    ] as $path
) $founder->call('GET', $path);
