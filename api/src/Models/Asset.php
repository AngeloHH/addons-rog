<?php

/**
 * Addons ROG - Stored asset model
 *
 * - Track upload ownership, private storage keys, scan results, and expiry.
 * - Expose a separate public UUID instead of the internal storage path.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Asset extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'assets';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'external_id' => ['type' => 'uuid', 'rules' => 'NOT NULL DEFAULT gen_random_uuid() UNIQUE'],
        'owner_id' => ['type' => 'bigint', 'rules' => 'REFERENCES accounts(id)'],
        'kind' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (kind IN (\'zip\',\'icon\',\'screenshot\',\'attachment\'))'],
        'storage_key' => ['type' => 'text', 'rules' => 'NOT NULL UNIQUE'],
        'filename' => ['type' => 'text', 'rules' => 'NOT NULL'],
        'mime_type' => ['type' => 'text', 'rules' => 'NOT NULL'],
        'size_bytes' => ['type' => 'bigint', 'rules' => 'NOT NULL CHECK (size_bytes > 0)'],
        'status' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (status IN (\'scanning\',\'clean\',\'warning\',\'blocked\',\'removed\'))'],
        'metadata' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
        'expires_at' => ['type' => 'timestamptz', 'rules' => ''],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (kind=\'attachment\' OR owner_id IS NOT NULL)'];
}
