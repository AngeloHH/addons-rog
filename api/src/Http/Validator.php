<?php

/**
 * Addons ROG - Request value validation
 *
 * - Validate scalar values and bounded lists with consistent field errors.
 * - Normalize email addresses and convert valid timestamps to UTC.
 */

declare(strict_types=1);

namespace Rog\Http;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

class Validator {

    // =========================================================================
    // FIELD ERRORS AND SCALAR VALUES
    // =========================================================================

    public static function fail(string $field, string $code = 'INVALID_VALUE'): never {
        throw new ApiException(422, 'VALIDATION_FAILED', [$field => [$code]]);
    }

    public static function text(mixed $value, string $field, int $min, int $max): string {
        if (
            !is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")
            || mb_strlen($value) < $min || mb_strlen($value) > $max
        ) self::fail($field);
        return $value;
    }

    public static function integer(mixed $value, string $field, int $min = 1, int $max = PHP_INT_MAX): int {
        if (
            is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false
            || (int)$value < $min || (int)$value > $max
        ) self::fail($field);
        return (int)$value;
    }

    public static function boolean(mixed $value, string $field): bool {
        // Boolean fields require JSON booleans rather than truthy strings or numbers.
        if (!is_bool($value)) self::fail($field);
        return $value;
    }

    public static function choice(mixed $value, string $field, array $values): mixed {
        if (!in_array($value, $values, true)) self::fail($field);
        return $value;
    }

    // =========================================================================
    // EMAIL ADDRESSES AND LISTS
    // =========================================================================

    public static function email(mixed $value): string {
        $value = self::text($value, 'email', 3, 254);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) self::fail('email');
        return strtolower($value);
    }

    public static function items(mixed $value, string $field, int $max): array {
        if (!is_array($value) || !array_is_list($value) || count($value) > $max) self::fail($field);
        return $value;
    }

    // =========================================================================
    // TIMESTAMP VALIDATION
    // =========================================================================

    public static function date(mixed $value, string $field): string {
        // Require an explicit timezone before converting the timestamp to UTC.
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D';
        if (!is_string($value) || !preg_match($pattern, $value)) self::fail($field);
        try {
            $date = new DateTimeImmutable($value); $errors = DateTimeImmutable::getLastErrors();
            // Reject invalid calendar dates even when the date parser can normalize them.
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) self::fail($field);
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        } catch (Exception) { self::fail($field); }
    }
}
