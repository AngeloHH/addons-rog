<?php

/**
 * Addons ROG - PostgreSQL initial schema tests
 *
 * - Verify explicit creation, empty-schema checks, and unchanged runtime connections.
 * - Check transactional rollback and reads without schema ownership or DDL privileges.
 */

declare(strict_types=1);

// =============================================================================
// DISPOSABLE DATABASE PREPARATION
// =============================================================================

require dirname(__DIR__) . '/support/results.php';
$config = require dirname(__DIR__, 2) . '/bootstrap.php';
if (
    getenv('ROG_TEST_MODE') !== 'disposable' || $config['db_user'] !== 'rog_test'
) throw new RuntimeException('Disposable database required');
use Rog\Database\{Db, Schema};
use Rog\Models\Setting;
$root = dirname(__DIR__, 2); $schema = new Schema($root);
if (($argv[1] ?? '') === 'prepare') {
    new Db($config, createDatabase: true);
    echo "Empty test database prepared explicitly\n";
    exit;
}
$checks = [];

// =============================================================================
// INITIAL SCHEMA ASSERTIONS
// =============================================================================

function initial_check(bool $condition, string $name): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $name);
    $checks[] = $name;
}

function initial_error(callable $callback, string $text): void {
    try { $callback(); } catch (Throwable $error) {
        initial_check(str_contains($error->getMessage(), $text), 'Rejected: ' . $text);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $text);
}

// =============================================================================
// EXPLICIT INSTALLATION AND EXISTING DATA
// =============================================================================

$db = new Db($config);
initial_check($schema->is_empty($db->pdo), 'Four parallel connections leave the prepared database empty');
$missing = array_replace(
    $config, ['dsn' => str_replace('dbname=rog_test_app', 'dbname=rog_test_absent', $config['dsn'])]
);
initial_error(fn() => new Db($missing), 'does not exist');
initial_check(
    !(bool)$db->value("SELECT EXISTS(SELECT 1 FROM pg_database WHERE datname='rog_test_absent')"),
    'Normal connections never create missing databases'
);
initial_error(
    fn() => new Db(array_replace($missing, ['create_database' => false]), createDatabase: true), 'does not exist'
);
$schema->create($db);
initial_check(
    (int)$db->value("SELECT count(*) FROM pg_tables WHERE schemaname='rog_design'") === 15,
    'Explicit installation creates all 15 model tables'
);
initial_check(
    $db->value("SELECT to_regclass('rog_design.schema_migrations')") === null,
    'Installation creates no migration history'
);
initial_check(
    $db->value("SELECT to_regclass('rog_design.catalog_metrics')") !== null,
    'Initial installation includes dependent SQL objects'
);
$setting = (new Setting($db))->create(['key' => 'initial_test', 'value' => (object) ['kept' => true]]);
initial_error(fn() => $schema->create($db), 'Database schema is not empty');
initial_check((new Setting($db))->get('initial_test')->value->kept, 'Refused reinstallation preserves existing data');

// =============================================================================
// RUNTIME PRESERVATION OF CUSTOM STRUCTURE
// =============================================================================

$db->query('ALTER TABLE auth_tokens DROP COLUMN data'); $db->query('ALTER TABLE settings ADD COLUMN custom_field text');
$again = new Db($config);
initial_check(
    !(bool)$again->value(
        "SELECT EXISTS(SELECT 1 FROM information_schema.columns WHERE "
            . "table_schema='rog_design' AND table_name='auth_tokens' AND column_name='data')"
    ),
    'Connection does not recreate missing model columns'
);
initial_check(
    (bool)$again->value(
        "SELECT EXISTS(SELECT 1 FROM information_schema.columns WHERE "
            . "table_schema='rog_design' AND table_name='settings' AND " . "column_name='custom_field')"
    ),
    'Connection preserves extra columns'
);
initial_check((new Setting($again))->get('initial_test')->value->kept, 'Repeated connection preserves saved values');
$db->query("ALTER TABLE auth_tokens ADD COLUMN data object_json NOT NULL DEFAULT '{}'");
$db->query('ALTER TABLE settings DROP COLUMN custom_field');

// =============================================================================
// RUNTIME WITHOUT SCHEMA OWNERSHIP
// =============================================================================

$db->query('CREATE ROLE rog_runtime LOGIN');
$db->query('ALTER ROLE rog_runtime PASSWORD ' . $db->pdo->quote($config['db_password']));
$db->query('GRANT USAGE ON SCHEMA rog_design TO rog_runtime'); $db->query('GRANT SELECT ON settings TO rog_runtime');
$runtime = new Db(array_replace($config, ['db_user' => 'rog_runtime']));
initial_check(
    (new Setting($runtime))->get('initial_test')->value->kept,
    'Runtime connects and reads without schema ownership or DDL permissions'
);
$setting->delete();

// =============================================================================
// FAILED INITIAL INSTALLATION AND RETRY
// =============================================================================

$fresh_config = array_replace(
    $config, ['dsn' => str_replace('dbname=rog_test_app', 'dbname=rog_test_initial_retry', $config['dsn'])]
);
$fresh = new Db($fresh_config, createDatabase: true);
class FailingInitialSchema extends Schema {

    public function export(string $driver = 'pgsql'): string {
        return parent::export($driver) . "\nSELECT * FROM missing_initial_install_table;";
    }
}
initial_error(fn() => (new FailingInitialSchema($root))->create($fresh), 'missing_initial_install_table');
initial_check($schema->is_empty($fresh->pdo), 'Failed initial installation rolls back all schema objects');
$schema->create($fresh);
initial_check(
    (int)$fresh->value("SELECT count(*) FROM pg_tables WHERE schemaname='rog_design'") === 15,
    'Initial installation can be retried after rollback'
);

// =============================================================================
// OCCUPIED NAMESPACE REJECTION
// =============================================================================

$occupied_config = array_replace(
    $config, ['dsn' => str_replace('dbname=rog_test_app', 'dbname=rog_test_initial_occupied', $config['dsn'])]
);
$occupied = new Db($occupied_config, createDatabase: true);
$occupied->pdo->exec("CREATE SCHEMA rog_design; CREATE DOMAIN rog_design.existing_type AS text");
initial_error(fn() => $schema->create($occupied), 'Database schema is not empty');
initial_check(
    $occupied->value("SELECT to_regclass('rog_design.accounts')") === null,
    'Occupied schema is rejected before any application table is created'
);
file_put_contents(
    test_result_path('initial-schema-results-pgsql.json'),
    json_encode(
        [
            'passed' => count($checks), 'checks' => $checks, 'concurrent_connections' => 4,
            'scope' => 'Disposable databases only; explicit initial creation and no runtime schema changes'
        ],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    )
);
echo count($checks), " initial schema checks passed\n";
