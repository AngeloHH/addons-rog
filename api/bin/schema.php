<?php

/**
 * Addons ROG - Initial schema command
 *
 * - Export the schema for the selected database driver.
 * - Create the initial schema only in an eligible empty database.
 */

declare(strict_types=1);

use Rog\Database\Db;
use Rog\Database\Schema;

// =============================================================================
// CONFIGURATION AND COMMAND MODE
// =============================================================================

$config = require dirname(__DIR__) . '/bootstrap.php';
$schema = new Schema(dirname(__DIR__)); $mode = $argv[1] ?? 'export';

// =============================================================================
// SCHEMA EXPORT
// =============================================================================

if ($mode === 'export') {
    $driver = $argv[2] ?? (strstr($config['dsn'], ':', true) ?: 'pgsql');
    echo $schema->export($driver); exit;
}

// =============================================================================
// INITIAL SCHEMA CREATION
// =============================================================================

$error = 'Use export|create (initial installation on an empty schema only)';
if ($mode !== 'create') throw new RuntimeException($error);

$db = new Db($config, createDatabase: true); $schema->create($db);
echo "Initial schema created from models\n";
