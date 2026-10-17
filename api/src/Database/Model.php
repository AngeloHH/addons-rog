<?php

/**
 * Addons ROG - Typed model persistence
 *
 * - Cast declared model fields and encode portable database values.
 * - Persist changed fields while rejecting stale updates and deletes.
 */

declare(strict_types=1);

namespace Rog\Database;

use InvalidArgumentException;
use LogicException;
use stdClass;

abstract class Model {

    // =========================================================================
    // MODEL DEFINITION AND RECORD STATE
    // =========================================================================

    public const TABLE = '';
    public const COLUMNS = [];
    public const CONSTRAINTS = [];
    private array $data = [];
    private array $original = [];
    private ?string $version = null;

    public function __construct(protected Db $db) {}

    // =========================================================================
    // FIELD ACCESS AND COLUMN METADATA
    // =========================================================================

    public function __get(string $name): mixed {
        $this->column($name); return $this->data[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void {
        $column = $this->column($name);
        // Generated fields and persisted primary keys cannot be assigned through the model.
        $immutable = str_contains($column['rules'], 'GENERATED ALWAYS') || (
            $name === $this->primary() && $this->version !== null
        );
        if ($immutable) throw new InvalidArgumentException('Primary keys are immutable');
        $this->data[$name] = $this->cast($name, $value);
    }

    public function __isset(string $name): bool { return isset($this->data[$name]); }

    private function column(string $name): array {
        return static::COLUMNS[$name] ?? throw new InvalidArgumentException('Unknown column: ' . $name);
    }

    private function primary(): string {
        foreach (static::COLUMNS as $name => $column) if (str_contains($column['rules'], 'PRIMARY KEY')) return $name;
        throw new LogicException('Model needs a primary key');
    }

    private function table(): string {
        $prefix = $this->db->driver === 'pgsql' ? 'rog_design.' : '';
        return $prefix . Db::identifier(static::TABLE);
    }

    // =========================================================================
    // PHP AND DATABASE VALUE CONVERSION
    // =========================================================================

    private function cast(string $name, mixed $value): mixed {
        $type = $this->column($name)['type'];
        if ($value === null) return null;

        if (in_array($type, ['bigint', 'integer', 'smallint'], true)) {
            $error = 'Expected integer: ' . $name;
            $invalid = filter_var($value, FILTER_VALIDATE_INT) === false || is_bool($value);
            if ($invalid) throw new InvalidArgumentException($error);
            return (int) $value;
        }

        if ($type === 'object_json') {
            if (is_string($value)) $value = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
            if (is_array($value) && ($value === [] || !array_is_list($value))) $value = (object) $value;
            if (!$value instanceof stdClass) throw new InvalidArgumentException('Expected JSON object: ' . $name);
            return $value;
        }

        if ($type === 'text[]') {
            $error = 'Expected text array';
            if (!is_array($value) || !array_is_list($value)) throw new InvalidArgumentException($error);
            foreach ($value as $item) if (!is_string($item)) throw new InvalidArgumentException($error);
            return $value;
        }

        if ($type === 'bytea' && is_resource($value)) return stream_get_contents($value);
        if ($type === 'timestamptz' && $this->db->driver !== 'pgsql' && is_string($value)) {
            return SqliteFunctions::timestamp($value);
        }
        if (!is_string($value)) throw new InvalidArgumentException('Expected string: ' . $name);
        return $value;
    }

    /**
     * Keep JSON objects, text arrays, and binary values compatible with each driver.
     */
    private function encode(string $name, mixed $value): mixed {
        if ($value === null) return null;
        $type = $this->column($name)['type'];
        if ($type === 'object_json') return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if ($type === 'bytea') return ($this->db->driver === 'pgsql' ? '\\x' : '') . bin2hex($value);

        if ($type === 'text[]') {
            if ($this->db->driver !== 'pgsql') return json_encode($value, JSON_THROW_ON_ERROR);
            $items = array_map(fn($v) => '"' . addcslashes($v, '\\"') . '"', $value);
            return '{' . implode(',', $items) . '}';
        }
        return $value;
    }

    // =========================================================================
    // ROW SELECTION AND HYDRATION
    // =========================================================================

    private function selection(): string {
        $columns = [];
        foreach (static::COLUMNS as $name => $column) {
            $field = Db::identifier($name);
            $columns[] = $column['type'] === 'text[]' ? 'array_to_json(' . $field . ')::text AS ' . $field : $field;
        }

        // Use PostgreSQL row metadata or the portable version column for optimistic concurrency.
        $version = $this->db->driver === 'pgsql' ? ',xmin::text AS _row_version' : ',_row_version';
        return implode(',', $columns) . $version;
    }

    private function hydrate(array $row): static {
        $model = new static($this->db); $model->version = (string) $row['_row_version'];
        foreach (static::COLUMNS as $name => $column) {
            $value = $row[$name]; $type = $column['type'];
            if ($type === 'bytea' && $value !== null && $this->db->driver !== 'pgsql') $value = hex2bin($value);
            if ($type === 'text[]' && $value !== null) $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            $model->data[$name] = $model->cast($name, $value);
            $model->original[$name] = $model->encode($name, $model->data[$name]);
        }
        return $model;
    }

    // =========================================================================
    // RECORD LOOKUP AND FILTERING
    // =========================================================================

    public function get(int|string $id): ?static {
        $sql = 'SELECT ' . $this->selection() . ' FROM ' . $this->table();
        $sql .= ' WHERE ' . Db::identifier($this->primary()) . '=:id';
        $row = $this->db->one($sql, ['id' => $id]);
        return $row === null ? null : $this->hydrate($row);
    }

    public function filter(
        array $filters = [], int $limit = 100, int $offset = 0,
        string $order = 'id', string $direction = 'ASC'
    ): array {
        if ($order === 'id' && !isset(static::COLUMNS['id'])) $order = $this->primary();
        $this->column($order); $error = 'Invalid pagination';
        $invalid = $limit < 1 || $limit > 500 || $offset < 0 || !in_array($direction, ['ASC', 'DESC'], true);
        if ($invalid) throw new InvalidArgumentException($error);

        $where = []; $params = [];
        foreach ($filters as $name => $value) {
            $this->column($name); $key = 'v' . count($params);
            $where[] = Db::identifier($name) . ($value === null ? ' IS NULL' : '=:' . $key);
            if ($value !== null) $params[$key] = $this->encode($name, $this->cast($name, $value));
        }

        $sql = 'SELECT ' . $this->selection() . ' FROM ' . $this->table();
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY ' . Db::identifier($order) . ' ' . $direction . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        return array_map(fn($row) => $this->hydrate($row), $this->db->all($sql, $params));
    }

    // =========================================================================
    // INSERTS, UPDATES, AND DELETES
    // =========================================================================

    public function create(array $data): static {
        $model = new static($this->db);
        foreach ($data as $name => $value) $model->__set($name, $value);
        return $model->save();
    }

    /**
     * Write changed fields and refresh the in-memory record from the returned row.
     */
    public function save(): static {
        $values = [];
        foreach ($this->data as $name => $value) {
            $encoded = $this->encode($name, $value);
            $changed = !array_key_exists($name, $this->original) || $encoded !== $this->original[$name];
            if ($changed) $values[$name] = $encoded;
        }
        if (!$values && $this->version !== null) return $this;

        $params = []; $fields = [];
        foreach ($values as $name => $value) {
            $key = 'v' . count($params); $fields[$name] = ':' . $key; $params[$key] = $value;
        }

        if ($this->version === null) {
            $sql = 'INSERT INTO ' . $this->table();
            if ($fields) {
                $columns = array_map([Db::class, 'identifier'], array_keys($fields));
                $sql .= ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $fields) . ')';
            } else $sql .= ' DEFAULT VALUES';
        } else {
            $changes = [];
            foreach ($fields as $name => $placeholder) $changes[] = Db::identifier($name) . '=' . $placeholder;
            $sql = 'UPDATE ' . $this->table() . ' SET ' . implode(',', $changes);
            $version = $this->db->driver === 'pgsql' ? 'xmin::text' : '_row_version';
            $sql .= ' WHERE ' . Db::identifier($this->primary()) . '=:pk AND ' . $version . '=:row_version';
            // Match the original row version so another writer cannot be silently overwritten.
            $params['pk'] = $this->data[$this->primary()]; $params['row_version'] = $this->version;
        }

        $row = $this->db->one($sql . ' RETURNING ' . $this->selection(), $params);
        if ($row === null) throw new Conflict('The row changed or was deleted');
        $fresh = $this->hydrate($row); $this->data = $fresh->data;
        $this->original = $fresh->original; $this->version = $fresh->version;
        return $this;
    }

    /**
     * Delete only the version originally loaded, then clear the in-memory record.
     */
    public function delete(): void {
        if ($this->version === null) throw new LogicException('Cannot delete an unsaved record');
        $version = $this->db->driver === 'pgsql' ? 'xmin::text' : '_row_version';
        $sql = 'DELETE FROM ' . $this->table() . ' WHERE ' . Db::identifier($this->primary());
        $sql .= '=:pk AND ' . $version . '=:version';
        $params = ['pk' => $this->data[$this->primary()], 'version' => $this->version];
        $query = $this->db->query($sql, $params);
        if ($query->rowCount() !== 1) throw new Conflict('The row changed or was deleted');
        $this->version = null; $this->data = $this->original = [];
    }

    /**
     * Expose JSON object fields as ordinary arrays for callers.
     */
    public function to_array(): array {
        $data = $this->data;
        foreach (static::COLUMNS as $name => $column) {
            if ($column['type'] === 'object_json' && isset($data[$name])) {
                $json = json_encode($data[$name], JSON_THROW_ON_ERROR);
                $data[$name] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            }
        }
        return $data;
    }
}
