<?php

/**
 * Addons ROG - Addon comment model
 *
 * - Store authored comments with optional ratings and expansion references.
 * - Keep creation, edit, and withdrawal timestamps for comment history.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Comment extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'comments';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'addon_id' => ['type' => 'bigint', 'rules' => 'NOT NULL REFERENCES addons(id)'],
        'author_id' => ['type' => 'bigint', 'rules' => 'NOT NULL REFERENCES accounts(id)'],
        'body' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (char_length(body) BETWEEN 1 AND 4000)'],
        'rating' => ['type' => 'smallint', 'rules' => 'CHECK (rating BETWEEN 1 AND 5)'],
        'expansion_id' => ['type' => 'expansion_code', 'rules' => ''],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
        'updated_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
        'withdrawn_at' => ['type' => 'timestamptz', 'rules' => ''],
    ];
    public const CONSTRAINTS = [];
}
