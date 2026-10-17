<?php

/**
 * Addons ROG - Addon submission model
 *
 * - Track drafts, submitted versions, revisions, and moderation decisions.
 * - Enforce required content and decision metadata for each lifecycle state.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Submission extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'submissions';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'owner_id' => ['type' => 'bigint', 'rules' => 'NOT NULL REFERENCES accounts(id)'],
        'kind' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (kind IN (\'addon\',\'version\'))'],
        'addon_id' => ['type' => 'bigint', 'rules' => 'REFERENCES addons(id)'],
        'request_id' => ['type' => 'bigint', 'rules' => 'REFERENCES requests(id)'],
        'status' => ['type' => 'text', 'rules' => 'NOT NULL DEFAULT \'draft\' CHECK (status IN (\'draft\',\'pending\',\'approved\',\'rejected\'))'],
        // The revision changes when the submission is updated and protects concurrent edits.
        'revision' => ['type' => 'integer', 'rules' => 'NOT NULL DEFAULT 1 CHECK (revision >= 1)'],
        'expansion_id' => ['type' => 'expansion_code', 'rules' => ''],
        'version_label' => ['type' => 'text', 'rules' => 'CHECK (char_length(version_label) BETWEEN 1 AND 60)'],
        'fields' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
        'updated_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
        'submitted_at' => ['type' => 'timestamptz', 'rules' => ''],
        'decided_at' => ['type' => 'timestamptz', 'rules' => ''],
        'reviewer_id' => ['type' => 'bigint', 'rules' => 'REFERENCES accounts(id)'],
        'withdrawn_at' => ['type' => 'timestamptz', 'rules' => ''],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK (kind <> \'version\' OR addon_id IS NOT NULL)', 'CHECK (status <> \'approved\' OR addon_id IS NOT NULL)', 'CHECK ((status=\'draft\') = (submitted_at IS NULL))', 'CHECK ((status IN (\'approved\',\'rejected\')) = (decided_at IS NOT NULL AND reviewer_id IS NOT NULL))', 'CHECK (status IN (\'approved\',\'rejected\') OR (decided_at IS NULL AND reviewer_id IS NULL))', 'CHECK (withdrawn_at IS NULL OR status=\'approved\')', 'CHECK (decided_at IS NULL OR decided_at >= submitted_at)', 'CHECK (status IN (\'approved\',\'rejected\') OR NOT (fields::jsonb ? \'decision\'))', 'CHECK (status<>\'rejected\' OR
    ((fields->\'decision\'->>\'ruleId\' IN (\'clean\',\'free\',\'credit\',\'version\',\'duplicates\',\'honest\',\'privacy\')) IS TRUE
     AND (jsonb_typeof(fields->\'decision\'->\'reason\')=\'string\') IS TRUE
     AND (char_length(fields->\'decision\'->>\'reason\') BETWEEN 1 AND 4000) IS TRUE))', 'CHECK (status=\'draft\' OR (expansion_id IS NOT NULL AND version_label IS NOT NULL
      AND (fields->>\'rulesAccepted\' = \'true\') IS TRUE
      AND (fields->>\'testedConfirmed\' = \'true\') IS TRUE))', 'CHECK (status=\'draft\' OR kind=\'version\' OR
    ((char_length(fields->>\'name\') BETWEEN 1 AND 120) IS TRUE
      AND (fields->>\'categoryId\' IN (\'pve\',\'pvp\',\'dps\',\'healing\',\'tank\',\'gui\',\'map\',\'professions\')) IS TRUE
      AND (char_length(fields->>\'summary\') BETWEEN 1 AND 120) IS TRUE))'];
}
