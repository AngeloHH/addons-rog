<?php

/**
 * Addons ROG - Initial schema installation
 *
 * - Export model definitions and supporting database objects.
 * - Create an empty schema or resume an owned MySQL installation journal.
 */

declare(strict_types=1);

namespace Rog\Database;

use PDO;
use PDOException;
use LogicException;
use RuntimeException;
use InvalidArgumentException;

class Schema {
    public function __construct(private string $root) {}

    // =========================================================================
    // SCHEMA ELIGIBILITY
    // =========================================================================

    public function is_empty(PDO $pdo): bool {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $query = "SELECT count(*) FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'";
            return !(bool) $pdo->query($query)->fetchColumn();
        }
        if ($driver === 'mysql') {
            $query = 'SELECT (SELECT count(*) FROM information_schema.tables WHERE table_schema=DATABASE())+';
            $query .= '(SELECT count(*) FROM information_schema.routines WHERE routine_schema=DATABASE())';
            return !(bool) $pdo->query($query)->fetchColumn();
        }

        $query = "SELECT EXISTS (SELECT 1 FROM pg_namespace n WHERE n.nspname='rog_design' AND (";
        $query .= 'EXISTS (SELECT 1 FROM pg_class c WHERE c.relnamespace=n.oid) ';
        $query .= 'OR EXISTS (SELECT 1 FROM pg_proc p WHERE p.pronamespace=n.oid) ';
        $query .= 'OR EXISTS (SELECT 1 FROM pg_type t WHERE t.typnamespace=n.oid)))';
        return !(bool) $pdo->query($query)->fetchColumn();
    }

    // =========================================================================
    // INITIAL SCHEMA CREATION
    // =========================================================================

    /**
     * Install the initial schema; this does not reconcile an existing application schema.
     */
    public function create(Db $db, ?string $installationId = null): void {
        if ($db->driver === 'mysql') { $this->create_mysql($db, $installationId); return; }
        $db->transaction(function () use ($db) {
            $db->lock('rog:install');
            if (!$this->is_empty($db->pdo)) throw new RuntimeException('Database schema is not empty');
            if ($db->driver === 'sqlite') {
                $statements = (new PortableSchema($this->root, 'sqlite'))->statements();
                foreach ($statements as $statement) $db->pdo->exec($statement);
            } else $db->pdo->exec($this->export());
        });
    }

    /**
     * Resume only the same installation identity and statement plan after implicit DDL commits.
     */
    private function create_mysql(Db $db, ?string $installationId): void {
        // MySQL commits DDL implicitly. Record each initial step, never delete existing objects.
        $db->exclusive('rog:install', function () use ($db, $installationId) {
            $statements = (new PortableSchema($this->root, 'mysql'))->statements();
            $plan = hash('sha256', implode("\n", $statements));
            $owner = 'rog:initial:' . hash('sha256', ($installationId ?? bin2hex(random_bytes(24))) . ':' . $plan);
            $query = "SELECT value FROM settings WHERE key='initial_schema'";
            $marker = $db->has_table('settings') ? $db->value($query) : false;
            $error = 'Database schema is not empty';

            if ($marker !== false) {
                $progress = json_decode($marker, true, 512, JSON_THROW_ON_ERROR);
                $invalid = $installationId === null || ($progress['id'] ?? null) !== $installationId ||
                    ($progress['plan'] ?? null) !== $plan ||
                    $db->value("SELECT value FROM settings WHERE key='installation'") !== false;
                if ($invalid) throw new RuntimeException($error);
            } else {
                if ($db->has_table('settings')) {
                    // The table comment closes the crash window between the first DDL and its progress row.
                    $query = 'SELECT table_comment FROM information_schema.tables ';
                    $query .= "WHERE table_schema=DATABASE() AND table_name='settings'";
                    $comment = $db->value($query);
                    $query = 'SELECT (SELECT count(*) FROM information_schema.tables WHERE table_schema=DATABASE())+';
                    $query .= '(SELECT count(*) FROM information_schema.routines WHERE routine_schema=DATABASE())';
                    $objects = (int) $db->value($query);
                    $invalid = $installationId === null || $comment !== $owner || $objects !== 1;
                    if ($invalid) throw new RuntimeException($error);
                } else {
                    if (!$this->is_empty($db->pdo)) throw new RuntimeException($error);
                    $this->execute_initial($db, $statements[0] . ' COMMENT=' . $db->pdo->quote($owner));
                }

                $progress = ['id' => $installationId, 'plan' => $plan, 'next' => 1];
                $query = "INSERT INTO settings (key,value) VALUES ('initial_schema',:value)";
                $db->query($query, ['value' => json_encode($progress, JSON_THROW_ON_ERROR)]);
            }

            // Inspect an object before replaying a step whose progress update may have been interrupted.
            for ($i = $progress['next']; $i < count($statements); $i++) {
                if (!$this->mysql_object_exists($db, $statements[$i])) $this->execute_initial($db, $statements[$i]);
                $progress['next'] = $i + 1;
                $query = "UPDATE settings SET value=:value WHERE key='initial_schema'";
                $db->query($query, ['value' => json_encode($progress, JSON_THROW_ON_ERROR)]);
            }
            if ($installationId === null) $db->query("DELETE FROM settings WHERE key='initial_schema'");
        });
    }

    // =========================================================================
    // INITIAL STATEMENTS AND OBJECT INSPECTION
    // =========================================================================

    protected function execute_initial(Db $db, string $statement): void {
        try { $db->pdo->exec($statement); }
        catch (PDOException $error) {
            if (getenv('ROG_TEST_MODE') === 'disposable') error_log('Initial SQL: ' . $statement);
            throw $error;
        }
    }

    private function mysql_object_exists(Db $db, string $statement): bool {
        if (preg_match('/^CREATE (TABLE|VIEW|FUNCTION|TRIGGER) "?([a-z_]+)"?/i', $statement, $m)) {
            [$catalog, $schema, $name] = match (strtoupper($m[1])) {
                'TABLE', 'VIEW' => ['tables', 'table_schema', 'table_name'],
                'FUNCTION' => ['routines', 'routine_schema', 'routine_name'],
                'TRIGGER' => ['triggers', 'trigger_schema', 'trigger_name'],
            };
            $query = "SELECT count(*) FROM information_schema.$catalog WHERE $schema=DATABASE() AND $name=:name";
            return (bool) $db->value($query, ['name' => $m[2]]);
        }
        if (preg_match('/^CREATE (?:UNIQUE )?INDEX ([a-z_0-9]+) ON "?([a-z_]+)"?/i', $statement, $m)) {
            $query = 'SELECT count(*) FROM information_schema.statistics ';
            $query .= 'WHERE table_schema=DATABASE() AND table_name=:table AND index_name=:name';
            return (bool) $db->value($query, ['table' => $m[2], 'name' => $m[1]]);
        }
        if (preg_match('/^ALTER TABLE "?([a-z_]+)"? ADD "?([a-z_0-9]+)"?/i', $statement, $m)) {
            $query = 'SELECT count(*) FROM information_schema.columns ';
            $query .= 'WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:name';
            return (bool) $db->value($query, ['table' => $m[1], 'name' => $m[2]]);
        }
        throw new LogicException('Unrecognized initial schema statement');
    }

    // =========================================================================
    // MODEL COLUMN EXPORT
    // =========================================================================

    private function column(string $name, array $column): string {
        $types = [
            'bigint', 'integer', 'smallint', 'text', 'text[]', 'uuid',
            'bytea', 'timestamptz', 'object_json', 'expansion_code', 'category_code',
        ];
        if (!in_array($column['type'], $types, true)) throw new InvalidArgumentException('Unknown column type');
        return Db::identifier($name) . ' ' . $column['type'] . ' ' . ($column['rules'] ?? '');
    }

    /**
     * Render engine-specific initial SQL without applying it to a database.
     */
    public function export(string $driver = 'pgsql'): string {
        $error = 'Unsupported database driver';
        if (!in_array($driver, ['pgsql', 'mysql', 'sqlite'], true)) throw new InvalidArgumentException($error);
        if ($driver === 'sqlite') {
            $statements = (new PortableSchema($this->root, $driver))->statements();
            return implode(";\n", $statements) . ";\n";
        }
        if ($driver === 'mysql') {
            $sql = "SET SESSION sql_mode='ANSI_QUOTES,IGNORE_SPACE,PIPES_AS_CONCAT,";
            $sql .= "NO_BACKSLASH_ESCAPES,STRICT_TRANS_TABLES';\n";
            $sql .= "SET NAMES utf8mb4 COLLATE utf8mb4_bin;\nDELIMITER $$\n";
            $statements = (new PortableSchema($this->root, $driver))->statements();
            return $sql . implode("$$\n", $statements) . "$$\nDELIMITER ;\n";
        }

        $sql = "CREATE SCHEMA IF NOT EXISTS rog_design;\nSET search_path=rog_design,pg_catalog;\n";
        $sql .= file_get_contents($this->root . '/database/domains.sql') . "\n";
        foreach (require $this->root . '/database/models.php' as $model) {
            $fields = [];
            foreach ($model::COLUMNS as $column => $definition) $fields[] = $this->column($column, $definition);
            $fields = array_merge($fields, $model::CONSTRAINTS);
            $sql .= 'CREATE TABLE ' . Db::identifier($model::TABLE) . " (\n  " . implode(",\n  ", $fields) . "\n);\n";
        }
        return $sql . file_get_contents($this->root . '/database/objects.sql');
    }
}
