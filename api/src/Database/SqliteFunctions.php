<?php

/**
 * Addons ROG - SQLite compatibility functions
 *
 * - Register PHP implementations of SQL helpers used by portable queries.
 * - Match normalization, JSON, timestamp, and UUID behavior across engines.
 */

declare(strict_types=1);

namespace Rog\Database;

use DateTimeImmutable;
use DateTimeZone;
use Normalizer;
use PDO;
use RuntimeException;

final class SqliteFunctions {

    // =========================================================================
    // NORMALIZED NAMES, TIMESTAMPS, AND UUIDS
    // =========================================================================

    public static function normalize(string $name): string {
        $error = 'The intl extension is required';
        if (!class_exists(Normalizer::class)) throw new RuntimeException($error);

        // Normalize compatibility forms before case folding and removing non-letter/digit characters.
        $name = mb_strtolower(Normalizer::normalize($name, Normalizer::FORM_KC), 'UTF-8');
        return preg_replace('/[^\p{L}\p{N}]/u', '', $name);
    }

    public static function timestamp(string $value): string {
        $date = new DateTimeImmutable($value);
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    public static function uuid(): string {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);
        $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4);
        return $uuid . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    // =========================================================================
    // JSON VALUE AND CONTAINMENT HELPERS
    // =========================================================================

    private static function value(?string $json, string $path): mixed {
        if ($json === null) return null;
        $value = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        foreach (explode('.', substr($path, 2)) as $key) $value = is_object($value) ? ($value->$key ?? null) : null;
        return $value;
    }

    /**
     * Preserve the distinction between a missing path and a JSON null value.
     */
    private static function json_value(?string $json, string $path): ?string {
        if ($json === null) return null;
        $value = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        foreach (explode('.', substr($path, 2)) as $key) {
            if (!is_object($value) || !property_exists($value, $key)) return null;
            $value = $value->$key;
        }
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private static function contains(mixed $value, mixed $part): bool {
        if (is_object($part)) {
            if (!is_object($value)) return false;
            foreach (get_object_vars($part) as $key => $item) {
                if (!property_exists($value, $key) || !self::contains($value->$key, $item)) return false;
            }
            return true;
        }
        if (is_array($part)) {
            if (!is_array($value)) return false;
            foreach ($part as $item) {
                $found = false;
                foreach ($value as $candidate) {
                    if (self::contains($candidate, $item)) { $found = true; break; }
                }
                if (!$found) return false;
            }
            return true;
        }
        $numeric = (is_int($value) || is_float($value)) && (is_int($part) || is_float($part));
        return $numeric ? $value == $part : $value === $part;
    }

    /**
     * Sort object keys recursively so JSON equality ignores object member order.
     */
    private static function canonical(mixed $value): mixed {
        if (is_object($value)) {
            $fields = get_object_vars($value); ksort($fields);
            return (object)array_map([self::class, 'canonical'], $fields);
        }
        return is_array($value) ? array_map([self::class, 'canonical'], $value) : $value;
    }

    // =========================================================================
    // SQL FUNCTION REGISTRATION
    // =========================================================================

    public static function register(PDO $pdo): void {
        $functions = [
            // Object concatenation replaces duplicate keys; other values follow array concatenation.
            'rog_json_concat' => [function($left, $right) {
                if ($left === null || $right === null) return null;
                $a = json_decode($left, false, 512, JSON_THROW_ON_ERROR);
                $b = json_decode($right, false, 512, JSON_THROW_ON_ERROR);
                $value = is_object($a) && is_object($b)
                    ? (object)array_replace(get_object_vars($a), get_object_vars($b))
                    : array_merge(is_array($a) ? $a : [$a], is_array($b) ? $b : [$b]);
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }, 2],
            'ceil' => [fn($v) => $v === null ? null : ceil($v), 1],
            'floor' => [fn($v) => $v === null ? null : floor($v), 1],
            'rog_timezone' => [function($tz, $v) {
                if ($v === null) return null;
                $date = new DateTimeImmutable($v, new DateTimeZone('UTC'));
                return $date->setTimezone(new DateTimeZone($tz))->format('Y-m-d H:i:s.u');
            }, 2],
            'rog_json_equal' => [function($a, $b) {
                $left = self::canonical(json_decode($a, false, 512, JSON_THROW_ON_ERROR));
                $right = self::canonical(json_decode($b, false, 512, JSON_THROW_ON_ERROR));
                return (int)(json_encode($left) === json_encode($right));
            }, 2],
            'normalize_name' => [fn($s) => $s === null ? null : self::normalize($s), 1],
            'lower' => [fn($s) => $s === null ? null : mb_strtolower($s, 'UTF-8'), 1],
            'char_length' => [fn($s) => $s === null ? null : mb_strlen($s, 'UTF-8'), 1],
            'regexp' => [function($pattern, $s) {
                if ($s === null) return null;
                return (int)(preg_match('~' . str_replace('~', '\\~', $pattern) . '~u', $s) === 1);
            }, 2],
            'repeat' => [fn($s, $n) => str_repeat($s, (int)$n), 2],
            'greatest' => [fn(...$args) => max($args), -1], 'least' => [fn(...$args) => min($args), -1],
            // Use UTC-compatible timestamp helpers for intervals, grouping, and date arithmetic.
            'rog_interval_seconds' => [fn($s) => Sql::seconds($s), 1],
            'rog_epoch' => [fn($s) => $s === null ? null : (float)(new DateTimeImmutable($s))->format('U.u'), 1],
            'rog_date_add' => [function($s, $seconds) {
                if ($s === null) return null;
                $date = new DateTimeImmutable($s); $interval = sprintf('%+.0f microseconds', $seconds * 1000000);
                return self::timestamp($date->modify($interval)->format('Y-m-d H:i:s.uP'));
            }, 2],
            'rog_date_part' => [function($unit, $s) {
                if ($s === null) return null;
                $formats = ['year' => 'Y', 'hour' => 'G', 'minute' => 'i', 'isodow' => 'N'];
                return (int)(new DateTimeImmutable($s))->format($formats[$unit]);
            }, 2],
            'rog_date_trunc' => [function($unit, $s) {
                if ($s === null) return null;
                $date = new DateTimeImmutable($s);
                return match($unit) {
                    'day' => $date->format('Y-m-d 00:00:00.000000'),
                    'minute' => $date->format('Y-m-d H:i:00.000000'),
                    'year' => $date->format('Y-01-01 00:00:00.000000'),
                    'week' => $date->modify('monday this week')->format('Y-m-d 00:00:00.000000'),
                };
            }, 2],
            'rog_json_text' => [function($json, $path) {
                $v = self::value($json, $path);
                return $v === null ? null : (is_string($v) ? $v : json_encode(
                    $v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                ));
            }, 2],
            'rog_json_value' => [[self::class, 'json_value'], 2],
            'rog_json_type' => [function($json) {
                if ($json === null) return null;
                $type = get_debug_type(json_decode($json, false, 512, JSON_THROW_ON_ERROR));
                return match($type) {
                    'stdClass' => 'object', 'array' => 'array', 'string' => 'string',
                    'bool' => 'boolean', 'null' => 'null', default => 'number',
                };
            }, 1],
            'json_contains' => [function($json, $part) {
                if ($json === null || $part === null) return null;
                $value = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
                $part = json_decode($part, false, 512, JSON_THROW_ON_ERROR);
                return (int)self::contains($value, $part);
            }, 2],
        ];
        foreach ($functions as $name => [$callback, $args]) {
            $pdo->sqliteCreateFunction($name, $callback, $args, PDO::SQLITE_DETERMINISTIC);
        }
        // Time and UUID generators are intentionally registered without the deterministic flag.
        $pdo->sqliteCreateFunction('rog_now', fn() => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'), 0);
        $pdo->sqliteCreateFunction('rog_uuid', fn() => self::uuid(), 0);
    }
}
