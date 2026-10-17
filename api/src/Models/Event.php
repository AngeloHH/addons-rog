<?php

/**
 * Addons ROG - Activity event model
 *
 * - Store activity records and optional actor, version, and client references.
 * - Require download events to declare whether they count toward metrics.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Event extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'events';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'kind' => ['type' => 'text', 'rules' => 'NOT NULL'],
        'actor_id' => ['type' => 'bigint', 'rules' => 'REFERENCES accounts(id)'],
        'submission_id' => ['type' => 'bigint', 'rules' => 'REFERENCES submissions(id)'],
        'client_key' => ['type' => 'bytea', 'rules' => ''],
        'occurred_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT clock_timestamp()'],
        'data' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (kind<>\'download\' OR (submission_id IS NOT NULL AND client_key IS NOT NULL
      AND (jsonb_typeof(data->\'counted\')=\'boolean\') IS TRUE))'];
}
