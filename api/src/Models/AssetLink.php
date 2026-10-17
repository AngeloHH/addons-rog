<?php

/**
 * Addons ROG - Asset relationship model
 *
 * - Attach an asset to one submission, addon, or message in a named slot.
 * - Constrain slot positions and prevent duplicate links within a target.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class AssetLink extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'asset_links';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'asset_id' => ['type' => 'bigint', 'rules' => 'NOT NULL REFERENCES assets(id)'],
        'submission_id' => ['type' => 'bigint', 'rules' => 'REFERENCES submissions(id) ON DELETE CASCADE'],
        'addon_id' => ['type' => 'bigint', 'rules' => 'REFERENCES addons(id)'],
        'message_id' => ['type' => 'bigint', 'rules' => 'REFERENCES messages(id)'],
        'slot' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (slot IN (\'zip\',\'icon\',\'screenshot\',\'attachment\'))'],
        'position' => ['type' => 'smallint', 'rules' => 'NOT NULL DEFAULT 0'],
        'data' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (num_nonnulls(submission_id,addon_id,message_id)=1)', 'CHECK ((slot IN (\'zip\',\'icon\') AND position=0)
      OR (slot=\'screenshot\' AND position BETWEEN 1 AND 4)
      OR (slot=\'attachment\' AND position BETWEEN 1 AND 5))', 'CHECK ((submission_id IS NOT NULL AND slot IN (\'zip\',\'icon\',\'screenshot\'))
      OR (addon_id IS NOT NULL AND slot IN (\'icon\',\'screenshot\'))
      OR (message_id IS NOT NULL AND slot=\'attachment\'))', 'UNIQUE NULLS NOT DISTINCT(submission_id,addon_id,message_id,slot,position)', 'UNIQUE NULLS NOT DISTINCT(submission_id,addon_id,message_id,asset_id)'];
}
