<?php

/**
 * Addons ROG - SQL dialect compilation
 *
 * - Translate supported PostgreSQL expressions for SQLite and MySQL.
 * - Keep literals and bound parameters separate while rewriting expressions.
 */

declare(strict_types=1);

namespace Rog\Database;

use LogicException;
use InvalidArgumentException;

final class Sql {
    public function __construct(public readonly string $driver) {}

    // =========================================================================
    // TOKENIZATION AND STATEMENT COMPILATION
    // =========================================================================

    public static function tokens(string $sql): array {
        $pattern = '/\s+|--[^\n]*|\/\*[\s\S]*?\*\/|\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*"|';
        $pattern .= '::|->>|->|@>|<@|\|\||=>|<=>|<>|!=|<=|>=|:[a-zA-Z_][a-zA-Z_0-9]*|';
        $pattern .= '[a-zA-Z_][a-zA-Z_0-9]*(?:\.[a-zA-Z_][a-zA-Z_0-9]*)*|[0-9]+(?:\.[0-9]+)?|[^\s]/';
        preg_match_all($pattern, $sql, $matches);
        // Discard SQL comments and whitespace while preserving quoted token contents.
        $keep = fn($t) => trim($t) !== '' && !str_starts_with($t, '--') && !str_starts_with($t, '/*');
        return array_values(array_filter($matches[0], $keep));
    }

    public function compile(string $sql): string {
        if ($this->driver === 'pgsql') return $sql;
        $tokens = self::tokens($sql);
        for ($j = 0; $j < count($tokens); $j++) {
            // Remove unsupported SQLite row locks or reposition MySQL locks after LIMIT.
            $locking = strtoupper($tokens[$j]) === 'FOR' &&
                in_array(strtoupper($tokens[$j + 1] ?? ''), ['UPDATE', 'SHARE'], true);
            if ($locking) {
                $length = strtoupper(implode(' ', array_slice($tokens, $j + 2, 2))) === 'SKIP LOCKED' ? 4 : 2;
                if ($this->driver === 'sqlite') { array_splice($tokens, $j, $length); $j--; }
                elseif (strtoupper($tokens[$j + $length] ?? '') === 'LIMIT') {
                    $lock = array_splice($tokens, $j, $length); array_splice($tokens, $j + 2, 0, $lock);
                }
            }
            $nullsLast = $this->driver === 'mysql' &&
                strtoupper(implode(' ', array_slice($tokens, $j, 3))) === 'DESC NULLS LAST';
            if ($nullsLast) array_splice($tokens, $j + 1, 2);
        }
        $i = 0; return $this->render($this->parse($tokens, $i));
    }

    // =========================================================================
    // EXPRESSION NODES AND RECURSIVE PARSING
    // =========================================================================

    private function render(array $nodes): string { return implode(' ', array_column($nodes, 's')); }
    private function node(string $sql, string $type = 'sql'): array { return ['s' => $sql, 'type' => $type]; }

    private function args(array $nodes): array {
        $args = [[]];
        foreach ($nodes as $node) {
            if ($node['s'] === ',') $args[] = [];
            else $args[array_key_last($args)][] = $node;
        }
        return $args;
    }

    /**
     * Parse nested groups before applying JSON operators, intervals, and comparisons.
     */
    private function parse(array $tokens, int &$i, ?string $end = null): array {
        $nodes = [];
        $keywords = [
            'IN', 'NOT', 'EXISTS', 'VALUES', 'WHERE', 'WHEN', 'AND', 'OR', 'ON',
            'AS', 'OVER', 'FILTER', 'CHECK', 'FROM', 'SELECT', 'BY', 'DISTINCT', 'THEN', 'ELSE',
        ];
        while ($i < count($tokens)) {
            $token = $tokens[$i++];
            if ($token === $end) break;
            if ($token === '(' || $token === '[') {
                $inside = $this->parse($tokens, $i, $token === '(' ? ')' : ']');
                $previous = $nodes ? $nodes[array_key_last($nodes)]['s'] : '';
                if ($token === '[' && strtoupper($previous) === 'ARRAY') {
                    array_pop($nodes); $nodes[] = $this->node('json_array(' . $this->render($inside) . ')', 'json');
                } elseif (
                    $token === '(' && preg_match('/^[a-z_][a-z_0-9.]*$/i', $previous) &&
                    !in_array(strtoupper($previous), $keywords, true)
                ) {
                    array_pop($nodes); $nodes[] = $this->function($previous, $inside);
                } else $nodes[] = ['s' => '(' . $this->render($inside) . ')', 'type' => 'group', 'inside' => $inside];
                continue;
            }
            if (strtoupper($token) === 'INTERVAL' && isset($tokens[$i]) && str_starts_with($tokens[$i], "'")) {
                $nodes[] = $this->node((string) self::seconds(trim($tokens[$i++], "'")), 'interval'); continue;
            }
            if ($token === '::') {
                $value = array_pop($nodes); $type = strtolower($tokens[$i++]);
                if (($tokens[$i] ?? '') === '[' && ($tokens[$i + 1] ?? '') === ']') { $type .= '[]'; $i += 2; }
                if (strtoupper($nodes[array_key_last($nodes)]['s'] ?? '') === 'FILTER') {
                    $nodes[] = $value + ['filterCast' => $type];
                } else $nodes[] = $this->cast($value['s'], $type);
                continue;
            }

            $type = str_starts_with($token, "'") ? 'literal' : 'sql';
            if ($type !== 'literal') {
                $token = preg_replace('/^rog_design\./', '', $token);
                if ($this->driver === 'mysql' && strtolower($token) === 'key') {
                    $previous = strtoupper($nodes[array_key_last($nodes)]['s'] ?? '');
                    if (!in_array($previous, ['PRIMARY', 'FOREIGN'], true)) $token = '"key"';
                }
            }
            $nodes[] = $this->node($token, $type);
        }

        // JSON paths bind before containment, comparisons, and arithmetic.
        foreach (['->', '->>', '@>', '<@', '~'] as $op) {
            for ($j = 1; $j < count($nodes) - 1; $j++) {
                if ($nodes[$j]['s'] === $op) {
                    $a = $nodes[$j - 1]['s']; $b = $nodes[$j + 1]['s'];
                    $result = match ($op) {
                        '->', '->>' => $this->json($a, $b, $op === '->>'),
                        '@>' => 'json_contains(' . $a . ',' . $b . ')',
                        '<@' => 'json_contains(' . $b . ',' . $a . ')',
                        '~' => $this->driver === 'mysql' ? "regexp_like($a,$b,'c')" : "regexp($b,$a)",
                    };
                    $node = $this->node($result, $op === '->' ? 'json' : 'sql');
                    array_splice($nodes, $j - 1, 3, [$node]); $j = max(0, $j - 2);
                }
            }
        }

        for ($j = 1; $j < count($nodes) - 1; $j++) {
            if (in_array($nodes[$j]['s'], ['+', '-'], true) && $nodes[$j + 1]['type'] === 'interval') {
                $seconds = ($nodes[$j]['s'] === '-' ? '-' : '') . '(' . $nodes[$j + 1]['s'] . ')';
                $result = $this->date_add($nodes[$j - 1]['s'], $seconds);
                array_splice($nodes, $j - 1, 3, [$this->node($result)]); $j = max(0, $j - 2);
            } elseif (
                $nodes[$j]['s'] === '||' && ($nodes[$j - 1]['type'] === 'json' || $nodes[$j + 1]['type'] === 'json')
            ) {
                $query = 'rog_json_concat(' . $nodes[$j - 1]['s'] . ',' . $nodes[$j + 1]['s'] . ')';
                array_splice($nodes, $j - 1, 3, [$this->node($query, 'json')]); $j = max(0, $j - 2);
            }
        }

        // SQL-standard aggregate filters are lowered for MySQL.
        for ($j = 1; $j < count($nodes) - 1; $j++) {
            if (strtoupper($nodes[$j]['s']) === 'FILTER') {
                $aggregate = $nodes[$j - 1]; $condition = $nodes[$j + 1]['inside'] ?? [];
                $invalid = !isset($aggregate['aggregate']) || strtoupper($condition[0]['s'] ?? '') !== 'WHERE';
                if ($invalid) throw new LogicException('Unsupported aggregate filter');
                array_shift($condition); $arg = $aggregate['argument']; $distinct = '';
                if (preg_match('/^DISTINCT\s+/i', $arg)) {
                    $distinct = 'DISTINCT '; $arg = preg_replace('/^DISTINCT\s+/i', '', $arg);
                }

                $sql = $aggregate['aggregate'] . '(' . $distinct . 'CASE WHEN ' . $this->render($condition);
                $sql .= ' THEN ' . ($arg === '*' ? '1' : $arg) . ' END)';
                $result = isset($nodes[$j + 1]['filterCast']) ?
                    $this->cast($sql, $nodes[$j + 1]['filterCast']) : $this->node($sql);
                array_splice($nodes, $j - 1, 3, [$result]); $j--;
            }
        }

        // ILIKE becomes an explicit case-insensitive comparison, independent of database collation.
        // char(92) keeps the escape character outside PDO's quoted-string parser.
        for ($j = 1; $j < count($nodes) - 1; $j++) {
            if (strtoupper($nodes[$j]['s']) === 'ILIKE') {
                $replacement = 'lower(' . $nodes[$j - 1]['s'] . ') LIKE lower(' . $nodes[$j + 1]['s'];
                $replacement .= ') ESCAPE char(92)';
                array_splice($nodes, $j - 1, 3, [$this->node($replacement)]); $j--;
            }
        }
        for ($j = 1; $j < count($nodes) - 4; $j++) {
            if (strtoupper($this->render(array_slice($nodes, $j, 4))) === 'IS NOT DISTINCT FROM') {
                $operator = $this->driver === 'mysql' ? ' <=> ' : ' IS ';
                $query = '(' . $nodes[$j - 1]['s'] . $operator . $nodes[$j + 4]['s'] . ')';
                array_splice($nodes, $j - 1, 6, [$this->node($query)]); $j--;
            }
        }
        return $nodes;
    }

    // =========================================================================
    // TYPE CASTS AND FUNCTION TRANSLATION
    // =========================================================================

    private function cast(string $value, string $type): array {
        if (in_array($type, ['jsonb', 'object_json', 'text[]'], true)) {
            $query = $this->driver === 'mysql' ? 'CAST(' . $value . ' AS JSON)' : 'json(' . $value . ')';
            return $this->node($query, 'json');
        }
        if ($type === 'interval') return $this->node('rog_interval_seconds(' . $value . ')', 'interval');
        if ($type === 'date') return $this->node('date(' . $value . ')');
        if (in_array($type, ['timestamptz', 'timestamp'], true)) return $this->node($value);

        $target = match ($type) {
            'int', 'integer', 'bigint', 'smallint', 'boolean' => $this->driver === 'mysql' ? 'SIGNED' : 'INTEGER',
            'numeric', 'decimal' => $this->driver === 'mysql' ? 'DECIMAL(30,8)' : 'REAL',
            'text', 'uuid' => $this->driver === 'mysql' ? 'CHAR' : 'TEXT',
            default => throw new LogicException('Unsupported cast: ' . $type),
        };
        return $this->node('CAST(' . $value . ' AS ' . $target . ')');
    }

    /**
     * Lower supported date, JSON, array, and aggregate functions into the selected dialect.
     */
    private function function(string $name, array $inside): array {
        $name = strtolower($name); $raw = $this->render($inside);
        $args = array_map(fn($a) => $this->render($a), $this->args($inside));
        if ($name === 'cast') {
            if (!preg_match('/^(.*) AS (\w+)$/is', $raw, $m)) throw new LogicException('Invalid cast');
            return $this->cast($m[1], strtolower($m[2]));
        }
        if ($name === 'make_interval') {
            $error = 'Unsupported interval';
            if (!preg_match('/^(days|hours|mins|secs) => (.*)$/is', $raw, $m)) throw new LogicException($error);
            $units = ['days' => 86400, 'hours' => 3600, 'mins' => 60, 'secs' => 1];
            return $this->node('(' . $m[2] . ') * ' . $units[strtolower($m[1])], 'interval');
        }
        if ($name === 'extract') {
            if (!preg_match('/^(\w+) FROM (.*)$/is', $raw, $m)) throw new LogicException('Invalid extract');
            $unit = strtolower($m[1]); $expr = $m[2];
            if ($unit === 'epoch') {
                $parts = preg_split('/ - /', $expr, 2); $sql = $this->epoch($parts[0]);
                if (count($parts) === 2) $sql = '(' . $sql . '-' . $this->epoch($parts[1]) . ')';
            } else {
                $sql = $this->driver === 'sqlite' ? "rog_date_part('$unit',$expr)" : (
                    $unit === 'isodow' ? '(weekday(' . $expr . ')+1)' : 'extract(' . $unit . ' FROM ' . $expr . ')'
                );
            }
            return $this->node($sql);
        }
        if ($name === 'date_trunc') {
            $query = $this->driver === 'sqlite' ? 'rog_date_trunc(' . $raw . ')' : match (trim($args[0], "'")) {
                'day' => "CAST(date_format($args[1],'%Y-%m-%d 00:00:00') AS DATETIME(6))",
                'minute' => "CAST(date_format($args[1],'%Y-%m-%d %H:%i:00') AS DATETIME(6))",
                'year' => "CAST(date_format($args[1],'%Y-01-01 00:00:00') AS DATETIME(6))",
                'week' => "CAST(date_sub(date($args[1]),INTERVAL weekday($args[1]) DAY) AS DATETIME(6))",
                default => throw new LogicException('Unsupported date truncation'),
            };
            return $this->node($query);
        }
        if ($name === 'jsonb_set') {
            $path = "'$." . trim($args[1], "'{}") . "'";
            return $this->node('json_set(' . $args[0] . ',' . $path . ',' . $args[2] . ')', 'json');
        }
        if ($name === 'decode' && ($args[1] ?? '') === "'hex'") return $this->node($args[0]);
        if ($name === 'encode' && ($args[1] ?? '') === "'hex'") return $this->node($args[0]);
        if ($name === 'to_jsonb') return $this->node($raw);
        if ($name === 'array_to_json') return $this->node($raw, 'json');
        if ($name === 'num_nonnulls') {
            $values = array_map(fn($a) => '(' . $a . ' IS NOT NULL)', $args);
            return $this->node('(' . implode('+', $values) . ')');
        }
        if ($name === 'now' || $name === 'clock_timestamp') {
            return $this->node($this->driver === 'mysql' ? 'UTC_TIMESTAMP(6)' : 'rog_now()');
        }
        if ($name === 'jsonb_typeof' && $this->driver === 'mysql') return $this->node('lower(json_type(' . $raw . '))');

        $name = match ($name) {
            'jsonb_build_object' => 'json_object', 'jsonb_build_array' => 'json_array',
            'jsonb_typeof' => $this->driver === 'sqlite' ? 'rog_json_type' : 'lower_json_type',
            'cardinality' => $this->driver === 'mysql' ? 'json_length' : 'json_array_length',
            'gen_random_uuid' => $this->driver === 'mysql' ? 'uuid' : 'rog_uuid',
            default => $name,
        };
        $type = in_array($name, ['json_object', 'json_array'], true) ? 'json' : 'sql';
        $node = $this->node($name . '(' . $raw . ')', $type); $aggregates = ['count', 'sum', 'avg', 'min', 'max'];
        if (in_array($name, $aggregates, true)) $node += ['aggregate' => $name, 'argument' => $raw];
        return $node;
    }

    // =========================================================================
    // JSON PATHS AND TIME EXPRESSIONS
    // =========================================================================

    public function json(string $value, string $key, bool $text): string {
        $path = "'$." . str_replace("''", "'", trim($key, "'")) . "'";
        if ($this->driver === 'sqlite') return ($text ? 'rog_json_text' : 'rog_json_value') . "($value,$path)";
        $expr = "json_extract($value,$path)";
        return $text ? "CASE WHEN json_type($expr)='NULL' THEN NULL ELSE json_unquote($expr) END" : $expr;
    }

    public function date_add(string $value, string $seconds): string {
        return $this->driver === 'mysql' ? "timestampadd(MICROSECOND,($seconds)*1000000,$value)" :
            "rog_date_add($value,$seconds)";
    }

    public function epoch(string $value): string {
        return $this->driver === 'mysql' ? "timestampdiff(MICROSECOND,'1970-01-01 00:00:00',$value)/1000000.0" :
            "rog_epoch($value)";
    }

    /**
     * Convert supported literal interval units into seconds for portable date arithmetic.
     */
    public static function seconds(string $interval): float {
        $pattern = '/^(-?[0-9.]+)\s+(microseconds?|seconds?|minutes?|hours?|days?)$/i';
        if (!preg_match($pattern, trim($interval), $m)) throw new InvalidArgumentException('Invalid interval');
        return (float) $m[1] * match (rtrim(strtolower($m[2]), 's')) {
            'microsecond' => 0.000001, 'second' => 1, 'minute' => 60, 'hour' => 3600, 'day' => 86400,
        };
    }
}
