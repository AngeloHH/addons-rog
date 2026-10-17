<?php

/**
 * Addons ROG - Report warning and reopening tests
 *
 * - Reject a duplicate reporter entry, resolve a warning, and reopen the report.
 */

$report = $author->call(
    'POST', '/api/reports',
    ['target' => ['kind' => 'addon', 'id' => $aid], 'type' => 'listing', 'text' => 'Please check the description'], 201
);
$report_id = $report['reportId'];
$author->call(
    'POST', '/api/reports',
    ['target' => ['kind' => 'addon', 'id' => $aid], 'type' => 'listing', 'text' => 'Please check the description'], 409
);
$founder->call('POST', '/api/admin/reports/' . $report_id . '/action', ['action' => 'warn', 'note' => 'Checked']);
$reports = $founder->call('GET', '/api/admin/reports')['items'];
check($reports[0]['closed'], 'Resolved report appears closed in the moderation list');
$founder->call('POST', '/api/admin/reports/' . $report_id . '/action', ['action' => 'reopen']);
