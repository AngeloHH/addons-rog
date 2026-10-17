<?php

/**
 * Addons ROG - API test entry point
 *
 * - Run the disposable test environment for the requested database driver.
 * - Report runner failures on standard error with a nonzero exit status.
 */

declare(strict_types=1);
require __DIR__ . '/support/TestRunner.php';
try { (new TestRunner($argv[1] ?? 'pgsql'))->run(); } catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
