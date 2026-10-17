<?php

/**
 * Addons ROG - Portable initial schema tests
 *
 * - Reject reinstallation and preserve custom structure during ordinary connections.
 * - Verify explicit database creation and ownership-bound MySQL DDL recovery.
 */

declare(strict_types=1);

// =============================================================================
// DISPOSABLE DATABASE GUARD AND ASSERTIONS
// =============================================================================

require dirname(__DIR__) . '/support/results.php';
$config = require dirname(__DIR__, 2) . '/bootstrap.php';
if (
    getenv('ROG_TEST_MODE') !== 'disposable' || $config['db_user'] !== 'rog_test'
) throw new RuntimeException('Disposable database required');
use Rog\Database\{Db, Schema};
$driver = strstr($config['dsn'], ':', true); $checks = [];
function initial_assert(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks[] = $label;
}

function initial_reject(callable $run, string $message): void {
    try { $run(); } catch (Throwable $error) {
        initial_assert(str_contains($error->getMessage(), $message), 'Reject ' . $message);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
}

// =============================================================================
// INSTALLED SCHEMA PRESERVATION
// =============================================================================

$schema = new Schema(dirname(__DIR__, 2)); $main = new Db($config);
initial_assert(!$main->has_table('schema_migrations'), 'No migration history');
initial_reject(fn() => $schema->create($main), 'Database schema is not empty');
$main->query('ALTER TABLE settings ADD custom_field text');
initial_assert(
    (new Db($config))->one('SELECT custom_field FROM settings LIMIT 1') === null,
    'Runtime preserves custom structure without adding settings'
);
$main->query('ALTER TABLE settings DROP COLUMN custom_field');

// =============================================================================
// EXPLICIT CREATION AND INTERRUPTED DDL
// =============================================================================

foreach (['first', 'middle'] as $point) {
    $freshConfig = array_replace(
        $config,
        [
            'dsn' => $driver === 'sqlite' ? 'sqlite:' . dirname(substr($config['dsn'], 7)) . '/initial-' . $point
                . '.sqlite' : preg_replace('/dbname=[^;]+/', 'dbname=rog_test_initial_' . $point, $config['dsn'])
        ]
    );
    initial_reject(fn() => new Db($freshConfig), $driver === 'sqlite' ? 'does not exist' : 'Unknown database');
    $fresh = new Db($freshConfig, createDatabase: true);
    initial_assert($schema->is_empty($fresh->pdo), 'Normal connection leaves fresh database empty: ' . $point);
    if ($driver === 'mysql') {
        // Inject a failure after a real initial table statement, then test
        // owner-specific resumption.
        $failing = new class(dirname(__DIR__, 2), $point) extends Schema {
            public function __construct(string $root, private string $point) {
                parent::__construct($root);
            }
            protected function execute_initial(Db $db, string $statement): void {
                parent::execute_initial($db, $statement);
                if (
                    str_starts_with(
                        $statement, 'CREATE TABLE "' . ($this->point === 'first' ? 'settings' : 'accounts') . '"'
                    )
                ) throw new RuntimeException('simulated DDL interruption');
            }
        };
        initial_reject(fn() => $failing->create($fresh, 'test-owner'), 'simulated DDL interruption');
        initial_reject(fn() => $schema->create($fresh, 'other-owner'), 'Database schema is not empty');
        $schema->create($fresh, 'test-owner');
        initial_assert($fresh->has_table('catalog_metrics'), 'Owned DDL resumes after ' . $point . ' interruption');
    } else $schema->create($fresh);
    initial_assert($fresh->has_table('accounts'), 'Initial creation completed: ' . $point);
}
file_put_contents(
    test_result_path('initial-schema-results-' . $driver . '.json'),
    json_encode(['passed' => count($checks), 'checks' => $checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
);
echo count($checks), " portable initial schema checks passed\n";
