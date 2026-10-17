<?php

/**
 * Addons ROG - Ordered HTTP integration suite
 *
 * - Execute named scenarios against a disposable database and real HTTP server.
 * - Share fixtures in the declared scenario order and write a temporary result report.
 */

declare(strict_types=1);

// =============================================================================
// DISPOSABLE ENVIRONMENT AND SHARED HELPERS
// =============================================================================

require dirname(__DIR__) . '/support/results.php';
$apiRoot = dirname(__DIR__, 2); $testRoot = dirname(__DIR__);
$config = require $apiRoot . '/bootstrap.php';
if (getenv('ROG_TEST_MODE') !== 'disposable' || $config['db_user'] !== 'rog_test')
    throw new RuntimeException('Tests require an explicitly disposable database with user rog_test');
$db = new Rog\Database\Db($config); $checks = [];
require $testRoot . '/support/assertions.php';
require $testRoot . '/support/HttpClient.php';
require $testRoot . '/support/fixtures.php';

// =============================================================================
// ORDERED SCENARIO MANIFEST
// =============================================================================

$scenarios = [
    'database/model-persistence.php', 'http/routing/route-handlers.php', 'http/authentication/guest-permissions.php',
    'http/authentication/registration.php', 'http/submissions/publish-addon.php', 'http/requests/fulfilled-request.php',
    'http/catalog/public-listings.php', 'http/comments/create-and-edit.php',
    'http/downloads/signed-files-and-packs.php', 'http/submissions/reject-addon.php',
    'http/reports/warn-and-reopen.php', 'http/profiles/follow-author.php', 'http/admin/account-management.php',
    'http/admin/dashboard-endpoints.php', 'http/catalog/listings-after-moderation.php',
    'http/contact/recipients.php', 'http/contact/moderation-group.php', 'http/contact/send-message.php',
    'notifications/delivery-worker.php', 'http/authentication/login-and-logout.php',
    'http/routing/invalid-requests.php', 'http/authentication/roles-and-csrf.php',
    'http/submissions/upload-ownership.php', 'database/binary-and-array-values.php',
    'validation/dates-and-passwords.php', 'http/requests/votes-and-moderation.php', 'http/reports/takedown.php',
    'http/submissions/versions-and-expiration.php', 'http/files/archive-validation.php',
    'http/files/image-permissions.php', 'http/authentication/moderator-invitations.php',
    'http/contact/attachment-permissions.php', 'http/admin/ban-account.php', 'http/profiles/private-fields.php',
    'http/admin/statistics.php', 'http/response-formats.php', 'database/sql-portability.php',
    'http/routing/canonical-endpoints.php', 'http/comments/single-current-review.php', 'http/catalog/taxonomy.php',
    'http/submissions/draft-fields.php', 'http/catalog/secondary-categories.php',
];
// Later scenarios reuse identities, uploads, and moderation state created by
// earlier scenarios.
foreach ($scenarios as $scenario) {
    $beforeChecks = count($checks);
    require $testRoot . '/' . $scenario;
    echo $scenario . ': ' . (count($checks) - $beforeChecks) . " checks passed\n";
}

// =============================================================================
// TEMPORARY TEST RESULTS
// =============================================================================

$report = [
    'passed' => count($checks), 'checks' => $checks, 'php' => PHP_VERSION, 'driver' => $db->driver,
    'databaseVersion' => $db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'scope' => 'Disposable database plus real PHP HTTP server; no production database or external email.'
];
file_put_contents(
    test_result_path('test-results-' . $db->driver . '.json'),
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
);
echo count($checks), " checks passed\n";
