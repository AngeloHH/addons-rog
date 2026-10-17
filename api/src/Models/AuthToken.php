<?php

/**
 * Addons ROG - Authentication token model
 *
 * - Store hashed session, verification, and invitation secrets with expiry.
 * - Associate each purpose with an account or invitation email as required.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class AuthToken extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'auth_tokens';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'public_id' => ['type' => 'uuid', 'rules' => 'NOT NULL DEFAULT gen_random_uuid() UNIQUE'],
        'purpose' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (purpose IN (\'session\',\'verification\',\'invitation\'))'],
        'account_id' => ['type' => 'bigint', 'rules' => 'REFERENCES accounts(id)'],
        'email' => ['type' => 'text', 'rules' => ''],
        // Persist only the secret hash; the token value is held by its recipient.
        'secret_hash' => ['type' => 'bytea', 'rules' => 'NOT NULL'],
        'expires_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL'],
        'consumed_at' => ['type' => 'timestamptz', 'rules' => ''],
        'data' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (expires_at > created_at)', 'CHECK ((purpose = \'invitation\' AND email IS NOT NULL)
      OR (purpose IN (\'session\',\'verification\') AND account_id IS NOT NULL AND email IS NULL))'];
}
