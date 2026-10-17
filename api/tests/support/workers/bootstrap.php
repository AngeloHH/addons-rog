<?php

/**
 * Addons ROG - Concurrent connection worker
 *
 * - Wait for the shared barrier before opening a disposable PostgreSQL connection.
 * - Require the schema to remain empty after an ordinary runtime connection.
 */

declare(strict_types=1);
$config = require dirname(__DIR__, 3) . '/bootstrap.php';
if (
    getenv('ROG_TEST_MODE') !== 'disposable' || $config['db_user'] !== 'rog_test'
) throw new RuntimeException('Disposable database required');
$barrier = $argv[1];
// Signal readiness before waiting for the parent to release all workers.
file_put_contents($barrier . '.' . $argv[2], 'ready');
$deadline = microtime(true) + 30;
while (!is_file($barrier)) {
    if (microtime(true) > $deadline) throw new RuntimeException('Bootstrap barrier timeout');
    usleep(10000);
}
$db = new Rog\Database\Db($config);
if (
    $db->value("SELECT to_regclass('rog_design.accounts')") !== null
) throw new RuntimeException('Connection created tables');
echo "Connection left empty schema unchanged\n";
