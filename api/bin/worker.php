<?php

/**
 * Addons ROG - Notification worker
 *
 * - Deliver a bounded batch of queued notifications.
 * - Refresh PostgreSQL catalog metrics after processing the batch.
 */

use Rog\App;
use Rog\Http\Request;
use Rog\Services\NotificationService;

// =============================================================================
// APPLICATION SETUP
// =============================================================================

$config = require dirname(__DIR__) . '/bootstrap.php';
$app = new App($config);
$app->request = new Request('GET', '/');

// =============================================================================
// QUEUED NOTIFICATIONS
// =============================================================================

$limit = (int) ($argv[1] ?? 100);
$count = (new NotificationService($app))->work($limit);
echo $count, " messages processed\n";

// =============================================================================
// POSTGRESQL CATALOG METRICS
// =============================================================================

$db = $app->db();
if ($db->driver === 'pgsql') {
    // Move this refresh to a separate command if it becomes too expensive.
    $query = 'REFRESH MATERIALIZED VIEW CONCURRENTLY rog_design.catalog_metrics';
    $db->query($query);
    echo "Metrics refreshed\n";
}
