<?php

/**
 * Addons ROG - Report takedown tests
 *
 * - Verify that takedown hides an addon and reopening the report does not restore it.
 */

$founder->call('POST', '/api/admin/reports/' . $report_id . '/action', ['action' => 'takedown']);
$guest->call('GET', '/api/addons/' . $slug, null, 404);
$founder->call('POST', '/api/admin/reports/' . $report_id . '/action', ['action' => 'reopen']);
$guest->call('GET', '/api/addons/' . $slug, null, 404);
// Restore only the fixture so version publishing can be checked separately.
$db->query('UPDATE addons SET withdrawn_at=NULL WHERE id=:id', ['id' => $aid]);
