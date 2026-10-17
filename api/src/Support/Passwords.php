<?php

/**
 * Addons ROG - Password hashing
 *
 * - Prehash passwords before bcrypt to avoid its input-length truncation.
 * - Recognize the hash prefix while retaining verification of older hashes.
 */

declare(strict_types=1);

namespace Rog\Support;

class Passwords {
    // SHA-384 avoids bcrypt's 72-byte truncation; marker supports future upgrades.
    public static function hash(string $password): string {
        $digest = base64_encode(hash('sha384', $password, true));
        return 'sha384:' . password_hash($digest, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Apply the matching prehash only when the stored hash declares the SHA-384
     * format.
     */
    public static function verify(string $password, string $hash): bool {
        return str_starts_with($hash, 'sha384:')
            ? password_verify(base64_encode(hash('sha384', $password, true)), substr($hash, 7))
            : password_verify($password, $hash);
    }
}
