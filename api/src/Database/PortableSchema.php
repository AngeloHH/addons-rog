<?php

/**
 * Addons ROG - SQLite and MySQL schema generation
 *
 * - Translate the shared model registry into an initial portable schema.
 * - Generate equivalent indexes, views, and state-transition triggers.
 */

declare(strict_types=1);

namespace Rog\Database;

use LogicException;

final class PortableSchema {
    private Sql $sql;
    private array $indexes = [];

    public function __construct(private string $root, private string $driver) { $this->sql = new Sql($driver); }

    // =========================================================================
    // INITIAL TABLES AND INDEXES
    // =========================================================================

    public function statements(): array {
        $statements = []; $this->indexes = [];
        if ($this->driver === 'mysql') {
            // Supply SQL helpers used by compiled expressions on MySQL.
            $query = 'CREATE FUNCTION rog_json_concat(a JSON,b JSON) RETURNS JSON DETERMINISTIC NO SQL ';
            $query .= 'BEGIN DECLARE keys_json JSON; DECLARE item INT DEFAULT 0; DECLARE path TEXT; ';
            $query .= 'DECLARE result JSON; IF a IS NULL OR b IS NULL THEN RETURN NULL; END IF; ';
            $query .= "IF json_type(a)='OBJECT' AND json_type(b)='OBJECT' THEN SET result=a; ";
            $query .= 'SET keys_json=json_keys(b); WHILE item<json_length(keys_json) DO ';
            $query .= "SET path=concat('$.',json_quote(json_unquote(json_extract(keys_json,concat('$[',item,']'))))); ";
            $query .= 'SET result=json_set(result,path,json_extract(b,path)); SET item=item+1; END WHILE; ';
            $query .= 'RETURN result; END IF; RETURN json_merge_preserve(a,b); END';
            $statements[] = $query;

            $query = 'CREATE FUNCTION rog_interval_seconds(value VARCHAR(64)) RETURNS DECIMAL(20,6) ';
            $query .= "DETERMINISTIC NO SQL RETURN CAST(substring_index(value,' ',1) AS DECIMAL(20,6))*";
            $query .= "CASE rtrim(substring_index(value,' ',-1)) WHEN 'days' THEN 86400 WHEN 'day' THEN 86400 ";
            $query .= "WHEN 'hours' THEN 3600 WHEN 'hour' THEN 3600 WHEN 'minutes' THEN 60 WHEN 'minute' THEN 60 ";
            $query .= "WHEN 'microsecond' THEN 0.000001 ELSE 1 END";
            $statements[] = $query;
        }

        $models = require $this->root . '/database/models.php';
        // The setup's durable ownership marker lives in settings during MySQL DDL.
        usort($models, fn($a, $b) => ($a::TABLE === 'settings' ? -1 : 0) <=> ($b::TABLE === 'settings' ? -1 : 0));
        foreach ($models as $model) {
            $table = $model::TABLE; $columns = []; $foreign = []; $checks = [];
            foreach ($model::COLUMNS as $name => $column) {
                $rules = $column['rules']; $type = $column['type'];
                if (str_contains($rules, 'GENERATED ALWAYS AS IDENTITY')) {
                    $identity = $this->driver === 'sqlite' ? ' INTEGER PRIMARY KEY AUTOINCREMENT' :
                        ' BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY';
                    $columns[] = Db::identifier($name) . $identity; continue;
                }

                $pattern = '/REFERENCES ([a-z_]+)\(([a-z_]+)\)( ON DELETE CASCADE)?/';
                if ($this->driver === 'mysql' && preg_match($pattern, $rules, $m)) {
                    $reference = 'FOREIGN KEY (' . Db::identifier($name) . ') REFERENCES ';
                    $reference .= Db::identifier($m[1]) . '(' . Db::identifier($m[2]) . ')' . ($m[3] ?? '');
                    $foreign[] = $reference; $rules = str_replace($m[0], '', $rules);
                }

                if ($type === 'text[]') {
                    $rules = 'NOT NULL CHECK (json_array_length(kinds)>0 ';
                    $rules .= 'AND json_contains(\'["name","slug","alias"]\',kinds))';
                }
                if ($this->driver === 'mysql') {
                    $pattern = '/normalize_name\(([a-z_]+)\)/';
                    $rules = preg_replace($pattern, "regexp_replace(lower($1),'[^[:alnum:]]','')", $rules);
                }

                $rules = $this->sql->compile($rules);
                if ($this->driver === 'mysql') $rules = str_replace('json_array_length', 'json_length', $rules);
                $pattern = '/DEFAULT (rog_now\(\)|rog_uuid\(\)|UTC_TIMESTAMP\(6\)|uuid\(\))/';
                $rules = preg_replace($pattern, 'DEFAULT ($1)', $rules);
                if ($this->driver === 'mysql' && in_array($type, ['object_json', 'text[]'], true)) {
                    $rules = preg_replace("/DEFAULT ('[^']*')/", 'DEFAULT ($1)', $rules);
                }

                $physical = $this->type($table, $name, $type);
                $columns[] = Db::identifier($name) . ' ' . $physical . ' ' . $rules;
                if ($type === 'object_json') {
                    $field = Db::identifier($name);
                    $check = $this->driver === 'sqlite' ? "json_valid($field) AND rog_json_type($field)" :
                        "json_type($field)";
                    $object = $this->driver === 'mysql' ? 'OBJECT' : 'object';
                    $checks[] = "CHECK ($check = '$object')";
                }
                if ($type === 'expansion_code') {
                    $checks[] = 'CHECK (' . Db::identifier($name) . " IN ('wotlk','mop','wod','bfa','sl','df','tww'))";
                }
                if ($type === 'category_code') {
                    $values = " IN ('pve','pvp','dps','healing','tank','gui','map','professions'))";
                    $checks[] = 'CHECK (' . Db::identifier($name) . $values;
                }
            }

            foreach ($model::CONSTRAINTS as $i => $constraint) {
                if (preg_match('/^UNIQUE NULLS NOT DISTINCT\s*\((.+)\)$/s', $constraint, $m)) {
                    $this->unique($table, $table . '_unique_' . $i, 'json_array(' . $m[1] . ')');
                } elseif ($this->driver === 'mysql' && str_contains($constraint, 'parent_id<>id')) {
                    // MySQL disallows CHECK expressions referring to AUTO_INCREMENT; trigger below.
                } else {
                    $missing = $this->driver === 'sqlite' ? "json_type(fields,'$.decision') IS NULL" :
                        "json_contains_path(fields,'one','$.decision')=0";
                    $constraint = str_replace("NOT (fields::jsonb ? 'decision')", $missing, $constraint);
                    if ($this->driver === 'mysql') {
                        $pattern = '/normalize_name\(([a-z_]+)\)/';
                        $replacement = "regexp_replace(lower($1),'[^[:alnum:]]','')";
                        $constraint = preg_replace($pattern, $replacement, $constraint);
                    }
                    $checks[] = $this->sql->compile($constraint);
                }
            }

            // Portable row versions replace PostgreSQL xmin for model concurrency checks.
            $columns[] = '_row_version BIGINT NOT NULL DEFAULT 1';
            if ($this->driver === 'mysql' && $table === 'requests') {
                $columns[] = '_name_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL';
            }
            $columns = array_merge($columns, $checks, $foreign);
            $query = 'CREATE TABLE ' . Db::identifier($table) . ' (' . implode(",\n", $columns) . ')';
            $options = $this->driver === 'mysql' ?
                ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin' : '';
            $statements[] = $query . $options;
        }

        // Reproduce expression and partial uniqueness rules for each engine.
        $this->unique('accounts', 'accounts_username_uq', 'lower(username)');
        $this->unique('accounts', 'accounts_email_uq', 'lower(email)');
        $this->unique('auth_tokens', 'auth_session_secret_uq', 'secret_hash', "purpose='session'");
        $where = "purpose='verification' AND consumed_at IS NULL";
        $this->unique('auth_tokens', 'auth_verification_uq', 'account_id', $where);
        $where = "purpose='invitation' AND consumed_at IS NULL";
        $this->unique('auth_tokens', 'auth_invitation_uq', 'lower(email)', $where);

        $expression = 'json_array(normalize_name(addon_name),expansion_id)';
        $where = "state IN ('open','volunteered','inQueue')";
        $this->unique('requests', 'requests_active_name_uq', $expression, $where);
        $expression = 'json_array(addon_id,expansion_id,version_label)';
        $where = "addon_id IS NOT NULL AND status IN ('pending','approved')";
        $this->unique('submissions', 'submissions_version_uq', $expression, $where);
        $expression = 'json_array(type,addon_id,submission_id,comment_id)';
        $this->unique('reports', 'reports_open_target_uq', $expression, 'closed_at IS NULL');

        $expression = "json_unquote(json_extract(data,'$.dedupeKey'))";
        $value = $this->driver === 'mysql' ? "json_extract(data,'$.dedupeKey')" : "data,'$.dedupeKey'";
        $null = $this->driver === 'mysql' ? 'NULL' : 'null';
        $where = "json_extract(data,'$.dedupeKey') IS NOT NULL AND json_type($value) <> '$null'";
        $this->unique('messages', 'messages_delivery_uq', $expression, $where);
        if ($this->driver === 'sqlite') {
            $replacement = "rog_json_text(data,'$.dedupeKey')";
            $this->indexes = array_map(fn($s) => str_replace($expression, $replacement, $s), $this->indexes);
        }

        $statements = array_merge($statements, $this->indexes);
        // Add supporting indexes for ownership, moderation queues, and activity lookups.
        $lookups = [
            'auth_tokens' => ['account_id', 'expires_at'], 'assets' => ['owner_id', 'expires_at'],
            'addons' => ['owner_id'], 'requests' => ['author_id', 'state,expansion_id,created_at'],
            'submissions' => ['addon_id,decided_at', 'owner_id,updated_at', 'request_id,status', 'status,submitted_at'],
            'comments' => ['addon_id,created_at', 'author_id'],
            'interactions' => ['addon_id', 'target_user_id', 'request_id,kind'],
            'reports' => ['addon_id', 'submission_id', 'comment_id'], 'report_entries' => ['reporter_id'],
            'messages' => ['sender_id', 'recipient_id', 'parent_id', 'status,available_at'],
            'asset_links' => ['asset_id', 'addon_id', 'message_id'],
            'events' => ['kind,occurred_at', 'actor_id,occurred_at', 'submission_id,client_key,occurred_at'],
        ];
        foreach ($lookups as $table => $indexes) {
            foreach ($indexes as $i => $fields) {
                $query = 'CREATE INDEX ' . $table . '_lookup_' . $i . ' ON ';
                $statements[] = $query . Db::identifier($table) . ' (' . $fields . ')';
            }
        }

        foreach ($statements as $i => $statement) {
            if (str_starts_with($statement, 'CREATE TABLE "settings"')) {
                array_splice($statements, $i, 1); array_unshift($statements, $statement); break;
            }
        }
        return array_merge($statements, $this->views(), $this->triggers($models));
    }

    // =========================================================================
    // PHYSICAL COLUMN TYPES AND UNIQUENESS
    // =========================================================================

    private function type(string $table, string $name, string $type): string {
        if ($this->driver === 'sqlite') {
            return in_array($type, ['bigint', 'integer', 'smallint'], true) ? 'INTEGER' : 'TEXT';
        }
        if ($type === 'text') {
            $sizes = [
                'key' => 255, 'username' => 32, 'email' => 254, 'slug' => 200,
                'name' => 120, 'addon_name' => 120, 'version_label' => 60,
                'password_hash' => 255, 'storage_key' => 255, 'filename' => 200,
                'mime_type' => 100, 'kind' => 64, 'status' => 64, 'state' => 64,
                'purpose' => 64, 'slot' => 64, 'type' => 64, 'resolution' => 64,
            ];
            $size = $sizes[$name] ?? null;
            return $size ? 'VARCHAR(' . $size . ')' : 'TEXT';
        }

        return match ($type) {
            'bigint' => 'BIGINT', 'integer' => 'INTEGER', 'smallint' => 'SMALLINT',
            'timestamptz' => 'DATETIME(6)', 'object_json', 'text[]' => 'JSON',
            'uuid' => 'CHAR(36)', 'expansion_code', 'category_code' => 'VARCHAR(16)',
            'bytea' => 'VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin',
            default => throw new LogicException('Unknown model type'),
        };
    }

    /**
     * Use partial indexes on SQLite and nullable unique keys on MySQL.
     */
    private function unique(string $table, string $name, string $expression, ?string $where = null): void {
        if ($this->driver === 'sqlite') {
            $query = 'CREATE UNIQUE INDEX ' . $name . ' ON ' . Db::identifier($table) . ' (' . $expression . ')';
            $this->indexes[] = $query . ($where ? ' WHERE ' . $where : '');
        } elseif ($table === 'requests') {
            // Stored functions cannot appear in generated columns; populate this key in a trigger.
            $query = 'ALTER TABLE requests ADD _active_name_key VARBINARY(1024) NULL, ADD UNIQUE INDEX ';
            $this->indexes[] = $query . $name . ' (_active_name_key)';
        } else {
            $field = '_u_' . $name;
            $value = $where ? 'CASE WHEN ' . $where . ' THEN ' . $expression . ' END' : $expression;
            $query = 'ALTER TABLE ' . Db::identifier($table) . ' ADD ' . Db::identifier($field);
            $query .= ' VARBINARY(1024) GENERATED ALWAYS AS (CAST(' . $value . ' AS CHAR)) VIRTUAL, ADD UNIQUE INDEX ';
            $this->indexes[] = $query . $name . ' (' . Db::identifier($field) . ')';
        }
    }

    // =========================================================================
    // DERIVED CATALOG AND ACCOUNT VIEWS
    // =========================================================================

    private function views(): array {
        $defs = [
            'published_versions' =>
                'SELECT s.id,s.addon_id,s.owner_id,s.expansion_id,s.version_label,s.fields,' .
                's.decided_at AS released_at,l.asset_id FROM submissions s JOIN addons a ON a.id=s.addon_id ' .
                "JOIN asset_links l ON l.submission_id=s.id AND l.slot='zip' JOIN assets f ON f.id=l.asset_id " .
                "WHERE s.status='approved' AND s.withdrawn_at IS NULL AND a.withdrawn_at IS NULL " .
                "AND f.status IN ('clean','warning')",
            'latest_versions' =>
                'SELECT id,addon_id,owner_id,expansion_id,version_label,fields,released_at,asset_id ' .
                'FROM (SELECT v.*,row_number() OVER(PARTITION BY addon_id,expansion_id ' .
                'ORDER BY released_at DESC,id DESC) AS position FROM published_versions v) ranked WHERE position=1',
            'current_ratings' =>
                'SELECT id,addon_id,author_id,body,rating,expansion_id,created_at,updated_at,withdrawn_at ' .
                'FROM (SELECT c.*,row_number() OVER(PARTITION BY addon_id,author_id ' .
                'ORDER BY updated_at DESC,id DESC) AS position FROM comments c WHERE rating IS NOT NULL) ' .
                'ranked WHERE position=1 AND withdrawn_at IS NULL',
            'addon_ratings' =>
                'SELECT addon_id,round(avg(rating),1) AS rating,count(*) AS votes ' .
                'FROM current_ratings GROUP BY addon_id',
            'account_state' =>
                "SELECT a.*,CASE WHEN access_level=2 THEN 'founder' WHEN access_level=1 THEN 'moderator' " .
                'WHEN EXISTS(SELECT 1 FROM addons d JOIN published_versions v ON v.addon_id=d.id ' .
                "WHERE d.owner_id=a.id) THEN 'author' ELSE 'player' END AS role," .
                "CASE WHEN banned_at IS NOT NULL THEN 'banned' WHEN suspended_until>now() THEN 'suspended' " .
                "ELSE 'active' END AS status FROM accounts a",
            'counted_downloads' => "SELECT * FROM events WHERE kind='download' AND data @> '{\"counted\":true}'",
            'submission_expiry' =>
                "SELECT id,CASE WHEN status='draft' THEN updated_at+interval '30 days' " .
                'END AS expires_at FROM submissions',
            'catalog_metrics' =>
                'SELECT a.id AS addon_id,coalesce(d.downloads,0) AS downloads,r.rating,' .
                'coalesce(r.votes,0) AS votes,now() AS refreshed_at FROM addons a ' .
                'LEFT JOIN (SELECT s.addon_id,count(*) AS downloads FROM counted_downloads e ' .
                'JOIN submissions s ON s.id=e.submission_id GROUP BY s.addon_id) d ON d.addon_id=a.id ' .
                'LEFT JOIN addon_ratings r ON r.addon_id=a.id',
        ];
        $out = [];
        foreach ($defs as $name => $query) $out[] = 'CREATE VIEW ' . $name . ' AS ' . $this->sql->compile($query);
        return $out;
    }

    // =========================================================================
    // STATE GUARDS AND ROW VERSIONS
    // =========================================================================

    private function triggers(array $models): array {
        $out = []; $same = fn($a, $b) => $this->driver === 'sqlite' ? "$a IS $b" : "($a <=> $b)";
        $new = "json_remove(NEW.fields,'$.decision')"; $old = "json_remove(OLD.fields,'$.decision')";
        $jsonSame = $this->driver === 'sqlite' ? "rog_json_equal($new,$old)" : "($new <=> $old)";
        $jsonValue = $this->driver === 'sqlite' ? 'rog_json_value' : 'json_extract';
        // Reject changes to immutable fields and transitions outside the submission/request lifecycle.
        $guards = [
            'addons' => [['NEW.slug<>OLD.slug', 'slug is immutable']],
            'submissions' => [
                ['NEW.owner_id<>OLD.owner_id OR NEW.kind<>OLD.kind', 'owner/kind are immutable'],
                [
                    "OLD.status<>'draft' AND (NOT ($jsonSame) OR NOT " .
                    $same('NEW.version_label', 'OLD.version_label') .
                    ' OR NOT ' . $same('NEW.expansion_id', 'OLD.expansion_id') .
                    ' OR NOT ' . $same('NEW.submitted_at', 'OLD.submitted_at') . ')', 'submitted content is immutable',
                ],
                [
                    'NOT ' . $same("$jsonValue(NEW.fields,'$.decision')", "$jsonValue(OLD.fields,'$.decision')") .
                    " AND NOT (OLD.status='pending' AND NEW.status IN ('approved','rejected'))",
                    'decision can only be written when deciding a pending submission',
                ],
                [
                    "OLD.status IN ('approved','rejected') AND (NOT " . $same('NEW.decided_at', 'OLD.decided_at') .
                    ' OR NOT ' . $same('NEW.reviewer_id', 'OLD.reviewer_id') . ')', 'decision metadata is immutable',
                ],
                ['NOT ' . $same('NEW.created_at', 'OLD.created_at'), 'creation date is immutable'],
                [
                    "NEW.status<>OLD.status AND NOT ((OLD.status='draft' AND NEW.status='pending') OR " .
                    "(OLD.status='pending' AND NEW.status IN ('approved','rejected')))",
                    'invalid submission transition',
                ],
                [
                    'NOT ' . $same('OLD.addon_id', 'NEW.addon_id') . " AND NOT (OLD.status='draft' OR " .
                    "(OLD.status='pending' AND NEW.status='approved' AND OLD.kind='addon' AND OLD.addon_id IS NULL))",
                    'addon link is immutable after submission',
                ],
            ],
            'requests' => [[
                "NEW.state<>OLD.state AND NOT ((OLD.state='open' AND NEW.state IN " .
                "('volunteered','inQueue','fulfilled','closed')) OR (OLD.state='volunteered' AND NEW.state IN " .
                "('open','inQueue','fulfilled','closed')) OR (OLD.state='inQueue' AND NEW.state IN " .
                "('open','volunteered','fulfilled')))", 'invalid request transition',
            ]],
            'interactions' => [['1=1', 'interactions use INSERT/DELETE, not UPDATE']],
            'messages' => [['NEW.parent_id=NEW.id', 'message cannot reference itself']],
        ];

        foreach ($models as $model) {
            $table = $model::TABLE; $pk = in_array($table, ['settings', 'name_claims'], true) ? 'key' : 'id';
            $checks = '';
            foreach ($guards[$table] ?? [] as [$condition, $message]) {
                $checks .= $this->driver === 'sqlite' ? "SELECT RAISE(ABORT,'$message') WHERE $condition;" :
                    "IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='$message'; END IF;";
            }

            if ($this->driver === 'sqlite') {
                if ($checks) $out[] = "CREATE TRIGGER guard_$table BEFORE UPDATE ON \"$table\" BEGIN $checks END";
                $changes = '_row_version=OLD._row_version+1';
                if ($table === 'submissions') {
                    $changes .= ',revision=OLD.revision+1,updated_at=';
                    $changes .= 'greatest(rog_now(),rog_date_add(OLD.updated_at,0.000001))';
                }
                if ($table === 'requests') $changes .= ',updated_at=rog_now()';
                $query = "CREATE TRIGGER version_$table AFTER UPDATE ON \"$table\" ";
                $query .= "WHEN NEW._row_version=OLD._row_version BEGIN UPDATE \"$table\" ";
                $out[] = $query . "SET $changes WHERE \"$pk\"=NEW.\"$pk\"; END";
            } else {
                $changes = 'SET NEW._row_version=OLD._row_version+1;';
                if ($table === 'submissions') {
                    $changes .= 'SET NEW.revision=OLD.revision+1; SET NEW.updated_at=';
                    $changes .= 'greatest(UTC_TIMESTAMP(6),timestampadd(MICROSECOND,1,OLD.updated_at));';
                }
                if ($table === 'requests') {
                    $changes .= 'SET NEW.updated_at=UTC_TIMESTAMP(6); SET NEW._active_name_key=';
                    $changes .= "CASE WHEN NEW.state IN ('open','volunteered','inQueue') ";
                    $changes .= 'THEN CAST(json_array(NEW._name_key,NEW.expansion_id) AS CHAR) END;';
                }
                $query = "CREATE TRIGGER guard_$table BEFORE UPDATE ON \"$table\" ";
                $out[] = $query . "FOR EACH ROW BEGIN $checks $changes END";
                if ($table === 'requests') {
                    $query = 'CREATE TRIGGER request_name BEFORE INSERT ON requests FOR EACH ROW ';
                    $query .= "SET NEW._active_name_key=CASE WHEN NEW.state IN ('open','volunteered','inQueue') ";
                    $out[] = $query . 'THEN CAST(json_array(NEW._name_key,NEW.expansion_id) AS CHAR) END';
                }
            }
        }

        // Completed requests keep their votes frozen on both insertion and deletion.
        foreach (['INSERT' => 'NEW', 'DELETE' => 'OLD'] as $event => $row) {
            $condition = "$row.kind='request_vote' AND (SELECT state FROM requests ";
            $condition .= "WHERE id=$row.request_id) IN ('fulfilled','closed')";
            $body = $this->driver === 'sqlite' ? "SELECT RAISE(ABORT,'request votes are frozen') WHERE $condition;" :
                "IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='request votes are frozen'; END IF;";
            $query = 'CREATE TRIGGER vote_' . strtolower($event) . ' BEFORE ' . $event . ' ON interactions ';
            $out[] = $query . ($this->driver === 'mysql' ? 'FOR EACH ROW ' : '') . "BEGIN $body END";
        }
        return $out;
    }
}
