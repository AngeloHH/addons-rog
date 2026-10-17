<?php

/**
 * Addons ROG - Stored ZIP rescan
 *
 * - Recheck a stored ZIP and persist its structural scan result.
 * - Report blocked submissions and queue notifications for their owners.
 */

declare(strict_types=1);

use Rog\App;
use Rog\Http\Request;
use Rog\Http\Validator;
use Rog\Services\FileService;
use Rog\Services\NotificationService;
use Rog\Services\ZipScanner;

// =============================================================================
// APPLICATION SETUP
// =============================================================================

$config = require dirname(__DIR__) . '/bootstrap.php';
$app = new App($config); $db = $app->db();
$id = Validator::integer($argv[1] ?? null, 'assetId');
$app->request = new Request('GET', '/');


// =============================================================================
// ASSET LOOKUP AND STRUCTURAL SCAN
// =============================================================================

$query = "SELECT * FROM assets WHERE id = :id AND kind = 'zip'";
$asset = $db->one($query, ['id' => $id]);
if (!$asset) throw new RuntimeException('ZIP asset not found');

$path = (new FileService($app))->path($asset);
$meta = (new ZipScanner())->scan($path);

// =============================================================================
// SCAN RESULT AND QUARANTINE REPORTS
// =============================================================================

$db->transaction(function () use ($db, $asset, $meta, $app) {
    $db->query('SELECT id FROM assets WHERE id = :id FOR UPDATE', ['id' => $asset['id']]);

    $query = 'UPDATE assets SET status = :status, metadata = CAST(:meta AS jsonb) WHERE id = :id';
    $params = [
        'status' => $meta['status'], 'id' => $asset['id'],
        'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
    ];
    $db->query($query, $params);
    // Clean or reviewable files need no automatic quarantine report.
    if ($meta['status'] !== 'blocked') return;

    $query = 'SELECT s.id, s.owner_id FROM submissions s ';
    $query .= 'JOIN asset_links l ON l.submission_id = s.id ';
    $query .= "WHERE l.asset_id = :id AND s.status IN ('pending', 'approved')";
    $submissions = $db->all($query, ['id' => $asset['id']]);

    foreach ($submissions as $submission) {
        // Serialize report creation so concurrent rescans reuse the open report.
        $db->lock('report:version:' . $submission['id'] . ':file');

        $query = "SELECT id FROM reports WHERE submission_id = :id AND type = 'file' ";
        $query .= 'AND closed_at IS NULL FOR UPDATE';
        $report = $db->one($query, ['id' => $submission['id']]);

        if (!$report) {
            $query = 'INSERT INTO reports(type, submission_id, details) ';
            $query .= "VALUES ('file', :id, '{\"autoQuarantined\":true,\"ruleId\":\"clean\"}') RETURNING id";
            $report = $db->one($query, ['id' => $submission['id']]);
        } else {
            $query = 'UPDATE reports SET details = details::jsonb ';
            $query .= "|| '{\"autoQuarantined\":true,\"ruleId\":\"clean\"}'::jsonb WHERE id = :id";
            $db->query($query, ['id' => $report['id']]);
        }

        // Record the scanner finding without requiring a human reporter.
        $query = 'INSERT INTO report_entries(report_id, reporter_id, body) ';
        $query .= "VALUES (:id, NULL, 'Structural scanner detected executable content') ";
        $query .= 'ON CONFLICT DO NOTHING';
        $db->query($query, ['id' => $report['id']]);

        $data = [
            'actorName' => 'scanner', 'ownerId' => (int) $submission['owner_id'],
            'actorRole' => 'system', 'targetId' => (int) $submission['id'],
            'targetKind' => 'version', 'targetLabel' => $asset['filename'],
        ];
        $query = 'INSERT INTO events(kind, submission_id, data) ';
        $query .= "VALUES ('quarantine', :submission, CAST(:data AS jsonb))";
        $params = ['submission' => $submission['id'], 'data' => json_encode($data)];
        $db->query($query, $params);

        $message = 'Please contact moderation about submission ' . $submission['id'];
        // Deduplicate owner notifications by submission and file contents.
        $key = 'quarantine:' . $submission['id'] . ':' . $meta['sha256'];
        (new NotificationService($app))->account(
            (int) $submission['owner_id'], 'File quarantined', $message, $key
        );
    }
});

echo json_encode(['assetId' => $id, 'status' => $meta['status']], JSON_THROW_ON_ERROR), PHP_EOL;
