<?php

/**
 * Addons ROG - Account model
 *
 * - Store identity, password hashes, access levels, and account restrictions.
 * - Keep optional public profile data separate from authentication fields.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Account extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'accounts';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'username' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (char_length(username) BETWEEN 3 AND 32)'],
        'email' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (char_length(email) BETWEEN 3 AND 254)'],
        'password_hash' => ['type' => 'text', 'rules' => 'NOT NULL'],
        'verified_at' => ['type' => 'timestamptz', 'rules' => ''],
        // Access levels: 0 = regular account, 1 = moderator, 2 = founder.
        'access_level' => ['type' => 'smallint', 'rules' => 'NOT NULL DEFAULT 0
    CONSTRAINT accounts_access_level_check CHECK (access_level IN (0,1,2))'],
        'banned_at' => ['type' => 'timestamptz', 'rules' => ''],
        'suspended_until' => ['type' => 'timestamptz', 'rules' => ''],
        'profile' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
        'last_seen_at' => ['type' => 'timestamptz', 'rules' => ''],
    ];
    public const CONSTRAINTS = [];
}
