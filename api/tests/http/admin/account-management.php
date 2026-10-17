<?php

/**
 * Addons ROG - Account administration tests
 *
 * - Check suspension, reactivation, staff messaging, and invitation endpoints.
 */

$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['status' => 'suspended', 'suspendDays' => 1]);
$accounts = $founder->call('GET', '/api/admin/accounts?q=OtherUser')['items'];
check($accounts[0]['status'] === 'suspended', 'Account suspension appears in the directory');
$other->call('POST', '/api/submissions', [], 403);
$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['status' => 'active']);
$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['role' => 'moderator']);
$accounts = $founder->call('GET', '/api/admin/accounts?q=OtherUser')['items'];
check($accounts[0]['role'] === 'moderator' && $accounts[0]['status'] === 'active', 'Role and reinstatement persist');
$founder->call('PATCH', '/api/admin/accounts/' . $other_id, ['role' => 'player']);
$founder->call(
    'POST', '/api/admin/accounts/' . $other_id . '/message',
    ['subject' => 'Test notice', 'message' => 'A test message'], 201
);
$founder->call('POST', '/api/admin/invitations', ['email' => 'invite@example.test'], 201);
