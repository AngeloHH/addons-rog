<?php

/**
 * Addons ROG - Maintenance cleanup
 *
 * - Remove stale drafts and reconcile their linked requests.
 * - Consume expired tokens and trim old rate-limit events.
 * - Delete expired assets that have no remaining links.
 */

use Rog\App;
use Rog\Http\Request;
use Rog\Services\FileService;
use Rog\Services\RequestService;

// =============================================================================
// APPLICATION SETUP
// =============================================================================

$config = require dirname(__DIR__) . '/bootstrap.php';
$app = new App($config);
$app->request = new Request('GET', '/');
$db = $app->db();

// =============================================================================
// EXPIRED DATABASE RECORDS
// =============================================================================

$db->transaction(function () use ($db, $app) {
    // Lock drafts untouched for 30 days; skip rows locked by other workers.
    $query = "SELECT id, request_id FROM submissions WHERE status = 'draft' ";
    $query .= "AND updated_at < now() - interval '30 days' FOR UPDATE SKIP LOCKED";
    $drafts = $db->all($query);

    foreach ($drafts as $draft) {
        // Remove draft links first and keep their asset IDs for expiration.
        $params = ['id' => $draft['id']];
        $query = 'DELETE FROM asset_links WHERE submission_id = :id RETURNING asset_id';
        $links = $db->all($query, $params);
        $db->query('DELETE FROM submissions WHERE id = :id', $params);

        // Expire detached assets only when no other links remain.
        foreach ($links as $link) {
            $query = 'UPDATE assets SET expires_at = now() WHERE id = :id ';
            $query .= 'AND NOT EXISTS(SELECT 1 FROM asset_links WHERE asset_id = :id)';
            $db->query($query, ['id' => $link['asset_id']]);
        }

        // Recalculate the request state after removing its draft.
        if ($draft['request_id']) {
            (new RequestService($app))->reconcile((int) $draft['request_id']);
        }
    }

    // Mark expired authentication tokens as consumed.
    $query = 'UPDATE auth_tokens SET consumed_at = now() ';
    $query .= 'WHERE expires_at < now() AND consumed_at IS NULL';
    $db->query($query);

    // Keep only the last two days of rate-limit history.
    $query = "DELETE FROM events WHERE kind = 'rateLimit' ";
    $query .= "AND occurred_at < now() - interval '2 days'";
    $db->query($query);
});

// =============================================================================
// EXPIRED FILES
// =============================================================================

$query = 'SELECT * FROM assets WHERE expires_at < now() ';
$query .= 'AND NOT EXISTS(SELECT 1 FROM asset_links WHERE asset_id = assets.id)';
$assets = $db->all($query);

foreach ($assets as $asset) {
    $db->transaction(function () use ($db, $app, $asset) {
        // Lock each candidate before checking whether it is still unused.
        $params = ['id' => $asset['id']];
        $query = 'SELECT * FROM assets WHERE id = :id FOR UPDATE';
        $locked = $db->one($query, $params);
        if (!$locked) return; // Removed by another cleanup process.

        $query = 'SELECT EXISTS(SELECT 1 FROM asset_links WHERE asset_id = :id)';
        $linked = $db->value($query, $params);
        if ($linked) return;  // Linked after the initial asset selection.

        // Keep the database row if deleting an existing file fails.
        $path = (new FileService($app))->path($asset);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Cannot delete expired file');
        }

        $db->query('DELETE FROM assets WHERE id = :id', $params);
    });
}

echo "Cleanup complete\n";
