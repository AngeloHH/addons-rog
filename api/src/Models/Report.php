<?php

/**
 * Addons ROG - Moderation report model
 *
 * - Group reports against one addon, submission, or comment target.
 * - Keep resolution and closure metadata consistent with each other.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Report extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'reports';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'type' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (type IN (\'file\',\'credit\',\'listing\',\'comment\'))'],
        'addon_id' => ['type' => 'bigint', 'rules' => 'REFERENCES addons(id)'],
        'submission_id' => ['type' => 'bigint', 'rules' => 'REFERENCES submissions(id)'],
        'comment_id' => ['type' => 'bigint', 'rules' => 'REFERENCES comments(id)'],
        'resolution' => ['type' => 'text', 'rules' => 'CHECK (resolution IN (\'warn\',\'takedown\',\'dismiss\'))'],
        'closed_at' => ['type' => 'timestamptz', 'rules' => ''],
        'details' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (num_nonnulls(addon_id,submission_id,comment_id)=1)', 'CHECK ((resolution IS NULL) = (closed_at IS NULL))', 'CHECK ((type=\'comment\') = (comment_id IS NOT NULL))'];
}
