<?php

/**
 * Addons ROG - Application setting model
 *
 * - Persist named JSON objects used by application services and installation.
 * - Use the setting key as the primary identifier.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Setting extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'settings';
    public const COLUMNS = [
        'key' => ['type' => 'text', 'rules' => 'PRIMARY KEY'],
        'value' => ['type' => 'object_json', 'rules' => 'NOT NULL'],
        'updated_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
    ];
    public const CONSTRAINTS = [];
}
