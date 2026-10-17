<?php

/**
 * Addons ROG - Normalized name claim model
 *
 * - Reserve a normalized name, slug, or alias for an addon or submission.
 * - Prevent the same normalized key from belonging to multiple targets.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class NameClaim extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'name_claims';
    public const COLUMNS = [
        'key' => ['type' => 'text', 'rules' => 'PRIMARY KEY CHECK (key <> \'\' AND key = normalize_name(key))'],
        'addon_id' => ['type' => 'bigint', 'rules' => 'REFERENCES addons(id)'],
        'submission_id' => ['type' => 'bigint', 'rules' => 'REFERENCES submissions(id) ON DELETE CASCADE'],
        'kinds' => ['type' => 'text[]', 'rules' => 'NOT NULL CHECK (cardinality(kinds)>0 AND kinds <@ ARRAY[\'name\',\'slug\',\'alias\']::text[])'],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (num_nonnulls(addon_id, submission_id)=1)'];
}
