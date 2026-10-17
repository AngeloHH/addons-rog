<?php

/**
 * Addons ROG - Founder account creation
 *
 * - Create a verified founder using the supplied username and email.
 * - Read the password from ROG_FOUNDER_PASSWORD instead of command arguments.
 */

use Rog\Database\Db;
use Rog\Http\Validator;
use Rog\Support\Passwords;

// =============================================================================
// CONFIGURATION AND INPUT VALIDATION
// =============================================================================

$config = require dirname(__DIR__) . '/bootstrap.php';

if (count($argv) !== 3) {
    $usage = 'Usage: php bin/create-founder.php username email; password via ROG_FOUNDER_PASSWORD';
    throw new RuntimeException($usage);
}

$password = getenv('ROG_FOUNDER_PASSWORD');
Validator::text($password, 'password', 12, 128);
$db = new Db($config);

// =============================================================================
// FOUNDER ACCOUNT
// =============================================================================

$query = 'INSERT INTO accounts(username, email, password_hash, verified_at, access_level) ';
$query .= 'VALUES (:name, :email, :hash, now(), 2)';
$params = [
    'name' => Validator::text($argv[1], 'username', 3, 24),
    'email' => Validator::email($argv[2]),
    // Store the password hash; the plaintext password is never persisted.
    'hash' => Passwords::hash($password),
];
$db->query($query, $params);

echo "Founder created\n";
