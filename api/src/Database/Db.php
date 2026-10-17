<?php

/**
 * Addons ROG - Portable database access
 *
 * - Connect to PostgreSQL, MySQL, or SQLite with consistent query results.
 * - Adapt SQL, bind values, and coordinate transactions and application locks.
 */

declare(strict_types=1);

namespace Rog\Database;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;
use LogicException;
use RuntimeException;
use InvalidArgumentException;

class Db {

    // =========================================================================
    // CONNECTION STATE
    // =========================================================================

    public readonly PDO $pdo;
    public readonly string $driver;
    public readonly Sql $sql;
    private bool $active = false;
    private array $locks = [];
    private string $databaseKey;

    // =========================================================================
    // CONNECTION AND DRIVER SETTINGS
    // =========================================================================

    public function __construct(array $config, bool $createDatabase = false) {
        $this->driver = strstr($config['dsn'], ':', true) ?: '';
        $error = 'Unsupported database driver';
        if (!in_array($this->driver, ['pgsql', 'mysql', 'sqlite'], true)) throw new RuntimeException($error);

        $this->sql = new Sql($this->driver);
        $this->databaseKey = hash('sha256', $config['dsn']);
        $this->pdo = $this->connect($config, $createDatabase);

        if ($this->driver === 'pgsql') {
            $query = "SET search_path=rog_design,pg_catalog; SET timezone='UTC'; ";
            $query .= "SET statement_timeout='15s'; SET lock_timeout='5s'";
            $this->pdo->exec($query);
        } elseif ($this->driver === 'mysql') {
            $query = "SET NAMES utf8mb4 COLLATE utf8mb4_bin; SET time_zone='+00:00'; ";
            $query .= "SET SESSION sql_mode='ANSI_QUOTES,IGNORE_SPACE,PIPES_AS_CONCAT,";
            $query .= 'NO_BACKSLASH_ESCAPES,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,';
            $query .= "NO_ENGINE_SUBSTITUTION'; SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED; ";
            $query .= 'SET SESSION innodb_lock_wait_timeout=15';
            $this->pdo->exec($query);
        } else {
            $query = 'PRAGMA foreign_keys=ON; PRAGMA busy_timeout=15000; PRAGMA recursive_triggers=OFF';
            $this->pdo->exec($query); SqliteFunctions::register($this->pdo);
        }

        if ($this->driver === 'mysql') {
            $this->databaseKey = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        }
    }

    /**
     * Create a missing database only when both the caller and configuration allow it.
     */
    private function connect(array $config, bool $createDatabase): PDO {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($this->driver === 'sqlite') {
            $path = substr($config['dsn'], 7);
            $error = 'SQLite requires an explicit filesystem path';
            if ($path === '' || $path === ':memory:' || str_starts_with($path, 'file:')) {
                throw new RuntimeException($error);
            }

            $error = 'SQLite database does not exist';
            if (!is_file($path) && (!$createDatabase || !($config['create_database'] ?? false))) {
                throw new RuntimeException($error);
            }

            // Restrict permissions when an explicitly authorized SQLite file is created.
            $mask = umask(0077);
            try { return new PDO($config['dsn'], null, null, $options); }
            finally { umask($mask); }
        }

        if ($this->driver === 'mysql') {
            $options[PDO::ATTR_TIMEOUT] = 5;
            if (!empty($config['db_ssl_ca'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $config['db_ssl_ca'];
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }

            try { return new PDO($config['dsn'], $config['db_user'], $config['db_password'], $options); }
            catch (PDOException $error) {
                if (!$createDatabase || !($config['create_database'] ?? false)) throw $error;
                if (($error->errorInfo[1] ?? 0) !== 1049) throw $error;
                if (!preg_match('/;dbname=([a-z_][a-z0-9_]{0,62})(?:;|$)/D', $config['dsn'], $match)) throw $error;

                $dsn = preg_replace('/;dbname=[^;]+/', '', $config['dsn']);
                $admin = new PDO($dsn, $config['db_user'], $config['db_password'], $options);
                $query = 'CREATE DATABASE IF NOT EXISTS `' . $match[1] . '` ';
                $admin->exec($query . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin');
                return new PDO($config['dsn'], $config['db_user'], $config['db_password'], $options);
            }
        }

        try { return new PDO($config['dsn'], $config['db_user'], $config['db_password'], $options); }
        catch (PDOException $error) {
            if (!$createDatabase || !($config['create_database'] ?? false)) throw $error;
            // Only the explicitly named database may be created, using the configured account.
            $pattern = '/(?:^pgsql:|;)\s*dbname=([a-z_][a-z0-9_]{0,62})(?=;|$)/D';
            if (!preg_match($pattern, $config['dsn'], $match)) throw $error;
            $name = $match[1];
            if ($name === 'postgres') throw $error;

            $dsn = preg_replace('/((?:^pgsql:|;)\s*dbname=)[^;]+/', '${1}postgres', $config['dsn']);
            $admin = new PDO($dsn, $config['db_user'], $config['db_password'], $options);
            $admin->exec("SET statement_timeout='15s'; SET lock_timeout='5s'");
            $lock = $admin->prepare('SELECT pg_advisory_lock(hashtextextended(:key,0))');
            $lock->execute(['key' => 'rog:database:' . $name]);

            try {
                $exists = $admin->prepare('SELECT EXISTS(SELECT 1 FROM pg_database WHERE datname=:name)');
                $exists->execute(['name' => $name]);
                $query = 'CREATE DATABASE ' . self::identifier($name) . " TEMPLATE template0 ENCODING 'UTF8'";
                if (!$exists->fetchColumn()) $admin->exec($query);
            } finally {
                $unlock = $admin->prepare('SELECT pg_advisory_unlock(hashtextextended(:key,0))');
                $unlock->execute(['key' => 'rog:database:' . $name]);
            }

            return new PDO($config['dsn'], $config['db_user'], $config['db_password'], $options);
        }
    }

    // =========================================================================
    // PORTABLE QUERY DISPATCH
    // =========================================================================

    public function query(string $sql, array $params = []): PDOStatement|Result {
        if ($this->driver !== 'pgsql') {
            if ($this->driver === 'mysql') [$sql, $params] = $this->request_name_key($sql, $params);
            if (preg_match('/^REFRESH MATERIALIZED VIEW/i', trim($sql))) return new Result([]);
            if (preg_match('/^SELECT normalize_name\(:(\w+)\)$/i', trim($sql), $m)) {
                $name = SqliteFunctions::normalize($params[$m[1]]);
                return new Result([['normalize_name' => $name]]);
            }

            // Separate a top-level RETURNING clause before compiling the remaining SQL.
            $tokens = Sql::tokens($sql); $depth = 0; $returning = null;
            foreach ($tokens as $i => $token) {
                if ($token === '(') $depth++;
                elseif ($token === ')') $depth--;
                elseif ($depth === 0 && strtoupper($token) === 'RETURNING') {
                    $returning = implode(' ', array_slice($tokens, $i + 1));
                    $sql = implode(' ', array_slice($tokens, 0, $i)); break;
                }
            }

            if ($returning !== null) return $this->returning($sql, $returning, $params);
            $ignore = preg_match('/\s+ON CONFLICT DO NOTHING\s*$/i', $sql) === 1;
            if ($ignore) $sql = preg_replace('/\s+ON CONFLICT DO NOTHING\s*$/i', '', $sql);
            $sql = $this->sql->compile($sql);

            try { return $this->execute($sql, $params); }
            catch (PDOException $error) {
                if ($ignore && self::unique_violation($error)) return new Result([]);
                throw $error;
            }
        }

        return $this->execute($sql, $params);
    }

    private function request_name_key(string $sql, array $params): array {
        // MySQL has no NFKC function. Store a digest of the same PHP-normalized name
        // used by SQLite, preserving the original display name and database uniqueness.
        if (!preg_match('/^\s*(INSERT INTO|UPDATE)\s+"?requests"?\b/i', $sql)) return [$sql, $params];
        if (str_contains($sql, '_name_key')) return [$sql, $params];
        $tokens = Sql::tokens($sql); $value = null;

        if (strtoupper($tokens[0]) === 'INSERT') {
            $insert = $this->insertion($tokens);
            $column = array_search('addon_name', $insert['columns'], true);
            $value = $column === false ? null : ($insert['values'][$column] ?? null);
            array_splice($tokens, $insert['valuesEnd'], 0, [',', ':rog_name_key']);
            array_splice($tokens, $insert['columnsEnd'], 0, [',', '"_name_key"']);
        } else {
            foreach ($tokens as $i => $token) {
                if (trim($token, '"') === 'addon_name' && ($tokens[$i + 1] ?? '') === '=') {
                    $value = [$tokens[$i + 2]];
                    array_splice($tokens, $i, 0, ['"_name_key"', '=', ':rog_name_key', ',']); break;
                }
            }
            if ($value === null) return [$sql, $params];
        }

        $error = 'Request name must be a bound value or string literal';
        if (count($value ?? []) !== 1) throw new LogicException($error);
        $token = $value[0]; $name = $this->bound_value($token, $params);
        if (!is_string($name)) throw new LogicException('Request name must be a string');
        $params['rog_name_key'] = hash('sha256', SqliteFunctions::normalize($name));
        return [implode(' ', $tokens), $params];
    }

    private function bound_value(string $token, array $params): mixed {
        if (str_starts_with($token, ':')) return $params[substr($token, 1)] ?? null;
        if (str_starts_with($token, "'")) return str_replace("''", "'", substr($token, 1, -1));
        return null;
    }

    /**
     * Split explicit INSERT columns and values without breaking nested expressions.
     */
    private function insertion(array $tokens): array {
        $open = array_search('(', $tokens, true); $close = array_search(')', $tokens, true);
        $keyword = array_search('VALUES', array_map('strtoupper', $tokens), true);
        $error = 'Insert requires explicit columns and values';
        if ($open === false || $close === false || $keyword === false || ($tokens[$keyword + 1] ?? '') !== '(') {
            throw new LogicException($error);
        }

        $columns = array_slice($tokens, $open + 1, $close - $open - 1);
        $columns = array_values(array_filter($columns, fn($t) => $t !== ','));
        $columns = array_map(fn($t) => trim($t, '"'), $columns);
        $values = [[]]; $depth = 0;

        for ($i = $keyword + 2; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token === ')' && $depth === 0) {
                return [
                    'columns' => $columns, 'values' => $values,
                    'columnsEnd' => $close, 'valuesEnd' => $i,
                ];
            }

            if ($token === ',' && $depth === 0) $values[] = [];
            else {
                $values[array_key_last($values)][] = $token;
                if ($token === '(') $depth++; elseif ($token === ')') $depth--;
            }
        }

        throw new LogicException('Invalid insert values');
    }

    // =========================================================================
    // PARAMETER BINDING AND RETURNED ROWS
    // =========================================================================

    private function execute(string $sql, array $params): PDOStatement {
        // Native MySQL prepares require a separate placeholder for every occurrence.
        $bound = []; $tokens = Sql::tokens($sql);
        foreach ($tokens as &$token) {
            if (preg_match('/^:([a-zA-Z_]\w*)$/D', $token, $m)) {
                if (!array_key_exists($m[1], $params)) throw new RuntimeException('Missing SQL parameter: ' . $m[1]);
                $name = 'p' . count($bound); $bound[$name] = $params[$m[1]]; $token = ':' . $name;
            }
        }
        unset($token);

        if ($bound || ($params && !array_is_list($params))) { $sql = implode(' ', $tokens); $params = $bound; }
        try { $query = $this->pdo->prepare($sql); }
        catch (PDOException $error) {
            if (getenv('ROG_TEST_MODE') === 'disposable') error_log($error->getMessage() . ' SQL: ' . $sql);
            throw $error;
        }

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : (
                is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR
            ));
            $query->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
        }

        try { $query->execute(); }
        catch (PDOException $error) {
            if (getenv('ROG_TEST_MODE') === 'disposable') error_log($error->getMessage() . ' SQL: ' . $sql);
            throw $error;
        }
        return $query;
    }

    /**
     * Buffer affected rows inside a transaction when the engine lacks native RETURNING.
     */
    private function returning(string $sql, string $selection, array $params): Result {
        return $this->transaction(function () use ($sql, $selection, $params) {
            $pattern = '/^(INSERT INTO|UPDATE|DELETE FROM)\s+((?:rog_design\.)?"?[a-z_]+"?)/i';
            if (!preg_match($pattern, $sql, $m)) throw new RuntimeException('Unsupported returning statement');
            $operation = strtoupper($m[1]); $table = str_replace(['rog_design.', '"'], '', $m[2]);
            $primary = in_array($table, ['settings', 'name_claims'], true) ? 'key' : 'id';
            $pk = self::identifier($primary); $target = self::identifier($table);

            if ($operation === 'INSERT INTO') {
                $statement = $this->query($sql, $params);
                if ($statement->rowCount() === 0) return new Result([]);
                $id = $this->pdo->lastInsertId();
                if ($primary !== 'id') {
                    $insert = $this->insertion(Sql::tokens($sql));
                    $position = array_search($primary, $insert['columns'], true);
                    $value = $position === false ? [] : $insert['values'][$position];
                    $id = count($value) === 1 ? $this->bound_value($value[0], $params) : null;
                }

                if ($id === null) throw new RuntimeException('Missing inserted primary key');
                $query = "SELECT $selection FROM $target WHERE $pk=:result_id";
                return new Result($this->all($query, ['result_id' => $id]), 1);
            }

            $tokens = Sql::tokens($sql); $depth = 0; $where = null;
            foreach ($tokens as $i => $token) {
                if ($token === '(') $depth++;
                elseif ($token === ')') $depth--;
                elseif (!$depth && strtoupper($token) === 'WHERE') {
                    $where = implode(' ', array_slice($tokens, $i + 1)); break;
                }
            }

            if ($where === null) throw new RuntimeException('Returning mutation requires a predicate');
            $query = "SELECT $pk FROM $target WHERE $where FOR UPDATE";
            $ids = array_column($this->all($query, $params), $primary);
            if (!$ids) return new Result([]);
            $arguments = []; $placeholders = [];
            foreach ($ids as $i => $id) { $arguments['result_' . $i] = $id; $placeholders[] = ':result_' . $i; }

            $read = "SELECT $selection FROM $target WHERE $pk IN (" . implode(',', $placeholders) . ')';
            // Capture deleted rows before mutation and updated rows after mutation.
            $rows = $operation === 'DELETE FROM' ? $this->all($read, $arguments) : [];
            $statement = $this->query($sql, $params);
            if ($operation === 'UPDATE') $rows = $this->all($read, $arguments);
            return new Result($rows, $statement->rowCount());
        });
    }

    // =========================================================================
    // DATABASE INSPECTION AND ROW ACCESS
    // =========================================================================

    public static function unique_violation(PDOException $error): bool {
        return ($error->errorInfo[0] ?? '') === '23505' || ($error->errorInfo[1] ?? 0) === 1062 || (
            ($error->errorInfo[1] ?? 0) === 19 && str_contains($error->getMessage(), 'UNIQUE constraint failed')
        );
    }

    public function has_table(string $name): bool {
        self::identifier($name);
        $mysql = 'SELECT count(*) FROM information_schema.tables ';
        $mysql .= 'WHERE table_schema=DATABASE() AND table_name=:name';
        $sqlite = "SELECT count(*) FROM sqlite_master WHERE type IN ('table','view') AND name=:name";

        return match ($this->driver) {
            'pgsql' => $this->value('SELECT to_regclass(:name)', ['name' => 'rog_design.' . $name]) !== null,
            'mysql' => (bool) $this->value($mysql, ['name' => $name]),
            'sqlite' => (bool) $this->value($sqlite, ['name' => $name]),
        };
    }

    public function all(string $sql, array $params = []): array { return $this->query($sql, $params)->fetchAll(); }
    public function one(string $sql, array $params = []): ?array {
        return $this->query($sql, $params)->fetch() ?: null;
    }
    public function value(string $sql, array $params = []): mixed { return $this->query($sql, $params)->fetchColumn(); }

    // =========================================================================
    // TRANSACTIONS AND APPLICATION LOCKS
    // =========================================================================

    public function transaction(callable $callback): mixed {
        // Nested callers reuse the outer transaction rather than creating a second boundary.
        if ($this->active || $this->pdo->inTransaction()) return $callback();
        if ($this->driver === 'sqlite') $this->pdo->exec('BEGIN IMMEDIATE'); else $this->pdo->beginTransaction();
        $this->active = true;

        try {
            $result = $callback();
            if ($this->driver === 'sqlite') $this->pdo->exec('COMMIT'); else $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->driver === 'sqlite') $this->pdo->exec('ROLLBACK');
            elseif ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        } finally {
            $this->active = false;
            foreach ($this->locks as $lock) $this->execute('SELECT RELEASE_LOCK(:key)', ['key' => $lock]);
            $this->locks = [];
        }
    }

    /**
     * Use transaction locks in PostgreSQL and named locks in MySQL.
     * SQLite writes are already serialized by the outer BEGIN IMMEDIATE transaction.
     */
    public function lock(string $key): void {
        $error = 'A transaction is required';
        if (!$this->active && !$this->pdo->inTransaction()) throw new RuntimeException($error);

        if ($this->driver === 'pgsql') {
            $this->query('SELECT pg_advisory_xact_lock(hashtextextended(:key,0))', ['key' => $key]);
        } elseif ($this->driver === 'mysql') {
            $key = hash('sha256', $this->databaseKey . ':' . $key);
            if (in_array($key, $this->locks, true)) return;
            $error = 'Timed out waiting for database lock';
            if ((int) $this->value('SELECT GET_LOCK(:key,15)', ['key' => $key]) !== 1) throw new Conflict($error);
            $this->locks[] = $key;
        }
    }

    /**
     * Hold a named lock across operations that may commit implicitly on MySQL.
     */
    public function exclusive(string $key, callable $callback): mixed {
        if ($this->driver !== 'mysql') {
            return $this->transaction(function () use ($key, $callback) { $this->lock($key); return $callback(); });
        }

        $name = hash('sha256', $this->databaseKey . ':' . $key);
        $error = 'Timed out waiting for database lock';
        if ((int) $this->value('SELECT GET_LOCK(:key,15)', ['key' => $name]) !== 1) throw new Conflict($error);
        try { return $callback(); }
        finally { $this->value('SELECT RELEASE_LOCK(:key)', ['key' => $name]); }
    }

    // =========================================================================
    // COUNTED DOWNLOAD EVENTS
    // =========================================================================

    public function record_download(
        int $version, ?int $actor, string $key, ?string $city = null, ?string $pack = null
    ): int {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) throw new InvalidArgumentException('Client HMAC required');
        if ($this->driver === 'pgsql') {
            $query = "SELECT rog_design.record_download(:version,:actor,decode(:key,'hex'),:city,:pack)";
            return (int) $this->value($query, compact('version', 'actor', 'key', 'city', 'pack'));
        }

        return $this->transaction(function () use ($version, $actor, $key, $city, $pack) {
            // Serialize the client/version pair before applying the 24-hour counting window.
            $this->lock('download:' . $version . ':' . $key);
            $query = 'SELECT id FROM published_versions WHERE id=:id';
            if (!$this->value($query, ['id' => $version])) throw new RuntimeException('Version unavailable');

            $query = 'SELECT count(*) FROM counted_downloads WHERE submission_id=:id ';
            $query .= "AND client_key=decode(:key,'hex') AND occurred_at>now()-interval '24 hours'";
            $counted = !(bool) $this->value($query, ['id' => $version, 'key' => $key]);
            $query = 'INSERT INTO events(kind,actor_id,submission_id,client_key,data) ';
            $query .= "VALUES ('download',:actor,:version,decode(:key,'hex'),CAST(:data AS jsonb)) RETURNING id";
            $data = json_encode(['counted' => $counted, 'city' => $city, 'packId' => $pack], JSON_THROW_ON_ERROR);
            $params = ['actor' => $actor, 'version' => $version, 'key' => $key, 'data' => $data];
            return (int) $this->value($query, $params);
        });
    }

    /**
     * Accept only application-controlled SQL identifiers and quote them consistently.
     */
    public static function identifier(string $name): string {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/D', $name)) throw new RuntimeException('Invalid SQL identifier');
        return '"' . $name . '"';
    }
}
