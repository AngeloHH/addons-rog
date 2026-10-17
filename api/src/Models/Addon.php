<?php

/**
 * Addons ROG - Addon model
 *
 * - Define a published addon identity, ownership, category, and display details.
 * - Retain withdrawal timestamps without removing the addon record.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Addon extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'addons';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'slug' => ['type' => 'text', 'rules' => 'NOT NULL UNIQUE CHECK (slug ~ \'^[a-z0-9]+(-[a-z0-9]+)*$\')'],
        'owner_id' => ['type' => 'bigint', 'rules' => 'NOT NULL REFERENCES accounts(id)'],
        'name' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (char_length(name) BETWEEN 1 AND 120)'],
        'category_id' => ['type' => 'category_code', 'rules' => 'NOT NULL'],
        'details' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
        'published_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
        'withdrawn_at' => ['type' => 'timestamptz', 'rules' => ''],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (normalize_name(name) <> \'\')'];
}
