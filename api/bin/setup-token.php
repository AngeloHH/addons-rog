<?php

/**
 * Addons ROG - Installer token command
 *
 * - Issue a setup token through the installer authorization service.
 * - Write failures to standard error and exit with a nonzero status.
 */

declare(strict_types=1);

use Rog\App;
use Rog\Http\Request;
use Rog\Services\InstallService;

// =============================================================================
// APPLICATION SETUP
// =============================================================================

$config = require dirname(__DIR__) . '/bootstrap.php';
$app = new App($config); $app->request = new Request('GET', '/setup/status');

// =============================================================================
// SETUP TOKEN
// =============================================================================

try {
    $token = (new InstallService($app))->issue_token();
    echo json_encode($token, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);
}
