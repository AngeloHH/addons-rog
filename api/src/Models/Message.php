<?php

/**
 * Addons ROG - Message and delivery model
 *
 * - Store contact conversations, staff messages, and queued notifications.
 * - Constrain delivery states and completion timestamps by message kind.
 */

declare(strict_types=1);

namespace Rog\Models;

use Rog\Database\Model;

class Message extends Model {

    // =========================================================================
    // TABLE AND COLUMN DEFINITION
    // =========================================================================

    public const TABLE = 'messages';
    public const COLUMNS = [
        'id' => ['type' => 'bigint', 'rules' => 'GENERATED ALWAYS AS IDENTITY PRIMARY KEY'],
        'kind' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (kind IN (\'contact\',\'staff\',\'notification\'))'],
        'sender_id' => ['type' => 'bigint', 'rules' => 'REFERENCES accounts(id)'],
        'recipient_id' => ['type' => 'bigint', 'rules' => 'REFERENCES accounts(id)'],
        'parent_id' => ['type' => 'bigint', 'rules' => 'REFERENCES messages(id)'],
        'status' => ['type' => 'text', 'rules' => 'NOT NULL CHECK (status IN (\'open\',\'answered\',\'queued\',\'processing\',\'sent\',\'failed\'))'],
        'data' => ['type' => 'object_json', 'rules' => 'NOT NULL DEFAULT \'{}\''],
        'created_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
        'completed_at' => ['type' => 'timestamptz', 'rules' => ''],
        'available_at' => ['type' => 'timestamptz', 'rules' => 'NOT NULL DEFAULT now()'],
    ];
    // Enforce relationships between fields in addition to individual column rules.
    public const CONSTRAINTS = ['CHECK ((kind=\'contact\' AND status IN (\'open\',\'answered\'))
      OR (kind IN (\'staff\',\'notification\') AND status IN (\'queued\',\'processing\',\'sent\',\'failed\')))', 'CHECK ((status IN (\'answered\',\'sent\')) = (completed_at IS NOT NULL))', 'CHECK (parent_id IS NULL OR parent_id<>id)'];
}
