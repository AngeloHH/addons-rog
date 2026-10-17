<?php

/**
 * Addons ROG - Report entry model
 *
 * - Record a reporter statement or an automated finding within a report.
 * - Limit each report/reporter pair to one entry, including system findings.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class ReportEntry extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'report_entries';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'report_id' => ['type' => 'bigint', 'rules' => 'NOT NULL REFERENCES reports(id)'],
        'reporter_id' => ['type' => 'bigint', 'rules' => 'REFERENCES accounts(id)'],
        'body' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (char_length(body) BETWEEN 1 AND 1000)'],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['UNIQUE NULLS NOT DISTINCT(report_id,reporter_id)'];
}
