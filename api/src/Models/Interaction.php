<?php

/**
 * Addons ROG - User interaction model
 *
 * - Represent addon favorites, author follows, request votes, and follows.
 * - Require one target compatible with the interaction kind and avoid duplicates.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Interaction extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'interactions';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'actor_id' => ['type' => 'bigint', 'rules' => 'NOT NULL REFERENCES accounts(id)'],
        'kind' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (kind IN (\'favorite\',\'follow_user\',\'request_vote\',\'request_follow\'))'],
        'addon_id' => ['type' => 'bigint', 'rules' => 'REFERENCES addons(id)'],
        'target_user_id' => ['type' => 'bigint', 'rules' => 'REFERENCES accounts(id)'],
        'request_id' => ['type' => 'bigint', 'rules' => 'REFERENCES requests(id)'],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (num_nonnulls(addon_id,target_user_id,request_id)=1)', 'CHECK ((kind=\'favorite\' AND addon_id IS NOT NULL)
      OR (kind=\'follow_user\' AND target_user_id IS NOT NULL AND target_user_id<>actor_id)
      OR (kind IN (\'request_vote\',\'request_follow\') AND request_id IS NOT NULL))', 'UNIQUE NULLS NOT DISTINCT (actor_id,kind,addon_id,target_user_id,request_id)'];
}
