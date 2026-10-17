<?php

/**
 * Addons ROG - Community request model
 *
 * - Track requested addons by expansion, author, and fulfillment state.
 * - Require a fulfilled addon reference exactly when the request is fulfilled.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class CommunityRequest extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'requests';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'author_id' => ['type' => 'bigint', 'rules' => 'NOT NULL REFERENCES accounts(id)'],
        'addon_name' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (char_length(addon_name) BETWEEN 1 AND 120)'],
        'expansion_id' => ['type' => 'expansion_code', 'rules' => 'NOT NULL'],
        'details' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (char_length(details) BETWEEN 1 AND 4000)'],
        'state' => ['type' => 'text', 'rules' => 'NOT NULL DEFAULT \'open\'
    CHECK (state IN (\'open\',\'volunteered\',\'inQueue\',\'fulfilled\',\'closed\'))'],
        'fulfilled_addon_id' => ['type' => 'bigint', 'rules' => 'REFERENCES addons(id)'],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
        'updated_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK ((state=\'fulfilled\') = (fulfilled_addon_id IS NOT NULL))', 'CHECK (normalize_name(addon_name) <> \'\')'];
}
