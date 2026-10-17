<?php

/**
 * Addons ROG - Account administration
 *
 * - Present account activity and apply permitted role or status changes.
 * - Queue staff messages and manage email-bound moderator invitations.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{ApiException, Validator as V, Response};

class AdminService extends Service {

    // =========================================================================
    // ACCOUNT PRESENTATION AND SEARCH
    // =========================================================================

    public function account(int $id): array {
        $row = $this->row('SELECT * FROM account_state WHERE id=:id', ['id' => $id]);
        $data = (new AuthService($this->app))->session_user($row);
        $query = "SELECT count(*) FROM submissions WHERE owner_id=:id AND status='approved'";
        $uploads = (int)$this->db()->value($query, ['id' => $id]);

        $query = 'SELECT coalesce(sum(m.downloads),0) FROM addons a ';
        $query .= 'JOIN catalog_metrics m ON m.addon_id=a.id WHERE a.owner_id=:id';
        $downloads = (int)$this->db()->value($query, ['id' => $id]);

        $query = 'SELECT count(*) FROM reports r LEFT JOIN addons a ON a.id=r.addon_id ';
        $query .= 'LEFT JOIN submissions s ON s.id=r.submission_id LEFT JOIN comments c ON c.id=r.comment_id ';
        $query .= 'WHERE coalesce(a.owner_id,s.owner_id,c.author_id)=:id';
        $reports = (int)$this->db()->value($query, ['id' => $id]);

        $query = "SELECT count(*) FROM events WHERE data->>'ownerId'=:id ";
        $query .= "AND (data->>'action' IN ('warn','takedown') OR kind='suspend')";
        $warnings = (int)$this->db()->value($query, ['id' => (string)$id]);
        $weights = $this->config('calculationRules');
        return $data + [
            'accessLevel' => (int)$row['access_level'], 'uploads' => $uploads,
            'downloads' => $downloads, 'reports' => $reports,
            'threat' => $row['status'] === 'banned' ? 100 : min(100, $warnings * ($weights['threatPerAction'] ?? 25)),
            'createdAt' => $this->date($row['created_at']), 'lastSeenAt' => $this->date($row['last_seen_at']),
            'online' => $row['last_seen_at'] !== null && strtotime($row['last_seen_at']) > time() - 300,
        ];
    }

    public function accounts(): Response {
        [$limit, $offset] = $this->pagination(10, 50);
        $where = ['true']; $params = []; $q = $this->request->query;
        if (isset($q['q'])) {
            $params['q'] = '%' . V::text($q['q'], 'q', 1, 100) . '%';
            $where[] = '(username ILIKE :q OR email ILIKE :q)';
        }
        $filters = [
            'role' => ['player', 'author', 'moderator', 'founder'],
            'status' => ['active', 'suspended', 'banned'],
        ];
        foreach ($filters as $key => $allowed) {
            if (isset($q[$key])) {
                $params[$key] = V::choice($q[$key], $key, $allowed); $where[] = $key . '=:' . $key;
            }
        }
        $base = ' FROM account_state WHERE ' . implode(' AND ', $where);
        $counts = array_fill_keys(['player', 'author', 'moderator', 'founder'], 0);
        $query = 'SELECT role,count(*)::int AS count FROM account_state GROUP BY role';
        foreach ($this->db()->all($query) as $r) $counts[$r['role']] = $r['count'];

        $total = (int)$this->db()->value('SELECT count(*)' . $base, $params);
        $query = 'SELECT id' . $base . ' ORDER BY last_seen_at DESC NULLS LAST,id DESC LIMIT :limit OFFSET :offset';
        $items = array_map(
            fn($r) => $this->account((int)$r['id']),
            $this->db()->all($query, $params + ['limit' => $limit, 'offset' => $offset])
        );
        $response = $this->page($items, $total, $offset, $limit, ['roleCounts' => $counts]);
        return $response;
    }

    // =========================================================================
    // ACCOUNT PERMISSIONS AND STATUS CHANGES
    // =========================================================================

    public function update_account(): array {
        $id = $this->id('accountId'); $body = $this->request->body;
        if ($id === $this->user_id()) throw new ApiException(403, 'FORBIDDEN');
        if (!isset($body['role']) && !isset($body['status']) && !isset($body['accessLevel'])) V::fail('body');
        $reason = V::text($body['reason'] ?? '', 'reason', 0, 2000);
        return $this->db()->transaction(function () use ($id, $body, $reason) {
            $row = $this->row('SELECT * FROM accounts WHERE id=:id FOR UPDATE', ['id' => $id]);
            $founder = (int)$this->app->user['access_level'] === 2;
            if (!$founder && (
                (int)$row['access_level'] > 0 || isset($body['role']) || isset($body['accessLevel'])
                || ($body['status'] ?? '') === 'banned'
            )) throw new ApiException(403, 'FORBIDDEN');

            $level = (int)$row['access_level'];
            if (isset($body['role'])) {
                $levels = ['player' => 0, 'author' => 0, 'moderator' => 1, 'founder' => 2];
                $level = $levels[V::choice($body['role'], 'role', ['player', 'author', 'moderator', 'founder'])];
            }
            if (isset($body['accessLevel'])) $level = V::integer($body['accessLevel'], 'accessLevel', 0, 2);
            $banned = $row['banned_at']; $suspended = $row['suspended_until']; $status = $body['status'] ?? null;
            if ($status !== null) {
                V::choice($status, 'status', ['active', 'suspended', 'banned']);
                $banned = $status === 'banned' ? gmdate('Y-m-d H:i:s') : null; $suspended = null;
                if ($status === 'suspended') {
                    if (isset($body['suspensionEndsAt'])) {
                        $suspended = V::date($body['suspensionEndsAt'], 'suspensionEndsAt');
                    } else {
                        $days = V::integer($body['suspendDays'] ?? 30, 'suspendDays', 1, 365);
                        $suspended = gmdate('Y-m-d H:i:s', time() + 86400 * $days);
                    }
                    if (
                        strtotime($suspended) <= time() || strtotime($suspended) > time() + 366 * 86400
                    ) V::fail('suspensionEndsAt');
                }
            }
            // Serialize founder changes and preserve at least one founder who
            // is not banned.
            if ((int)$row['access_level'] === 2 && ($level !== 2 || $banned !== null)) {
                $this->db()->lock('founder-count');
                $query = 'SELECT count(*) FROM accounts WHERE access_level=2 AND banned_at IS NULL';
                if ((int)$this->db()->value($query) <= 1) throw new ApiException(409, 'STATE_CONFLICT');
            }
            $query = 'UPDATE accounts SET access_level=:level,banned_at=:banned,';
            $query .= 'suspended_until=:suspended WHERE id=:id';
            $this->db()->query($query, [
                'level' => $level, 'banned' => $banned, 'suspended' => $suspended, 'id' => $id,
            ]);
            // Revoke active sessions immediately when an account is banned.
            if ($banned !== null) {
                $query = "UPDATE auth_tokens SET consumed_at=now() WHERE account_id=:id ";
                $query .= "AND purpose='session' AND consumed_at IS NULL";
                $this->db()->query($query, ['id' => $id]);
            }
            $kind = $status === 'banned' ? 'ban' : ($status === 'suspended' ? 'suspend' : 'roleChange');
            $this->audit($kind, 'account', $id, [
                'ownerId' => $id, 'targetLabel' => $row['username'], 'detail' => $reason,
                'before' => [
                    'accessLevel' => (int)$row['access_level'],
                    'bannedAt' => $row['banned_at'], 'suspendedUntil' => $row['suspended_until'],
                ],
                'after' => ['accessLevel' => $level, 'bannedAt' => $banned, 'suspendedUntil' => $suspended],
            ]);
            return ['account' => $this->account($id)];
        });
    }

    // =========================================================================
    // STAFF MESSAGES
    // =========================================================================

    /**
     * Persist the staff message and optionally queue its email copy in the same
     * transaction.
     */
    public function message(): array {
        $this->rate('staff-mail:' . $this->user_id(), 30, 3600); $id = $this->id('accountId');
        $subject = V::text($this->request->input('subject'), 'subject', 1, 200);
        $body = V::text($this->request->input('body', $this->request->input('message')), 'message', 1, 10000);
        $copy = V::boolean($this->request->input('sendEmailCopy', true), 'sendEmailCopy');
        return $this->db()->transaction(function () use ($id, $subject, $body, $copy) {
            $this->row('SELECT id FROM accounts WHERE id=:id', ['id' => $id]);
            $query = "INSERT INTO messages(kind,sender_id,recipient_id,status,data,completed_at) ";
            $query .= "VALUES ('staff',:sender,:recipient,:status,CAST(:data AS jsonb),";
            $query .= "CASE WHEN :complete THEN now() END) RETURNING *";
            $row = $this->row($query, [
                'sender' => $this->user_id(), 'recipient' => $id, 'status' => $copy ? 'queued' : 'sent',
                'data' => $this->encode(['subject' => $subject, 'body' => $body, 'sendEmailCopy' => $copy]),
                'complete' => !$copy,
            ]);
            if ($copy) {
                $email = $this->db()->value('SELECT email FROM accounts WHERE id=:id', ['id' => $id]);
                (new NotificationService($this->app))->email(
                    $email, $subject, $body, 'staff:' . $row['id'], $id, (int)$row['id']
                );
            }
            $this->audit('message', 'account', $id, ['messageId' => (int)$row['id']]);
            return [
                'messageId' => $this->external_id($row['id']), 'status' => $copy ? 'queued' : 'sent',
                'sentAt' => $copy ? null : $this->date($row['created_at']),
            ];
        });
    }

    // =========================================================================
    // MODERATOR INVITATIONS
    // =========================================================================

    public function invite(): array {
        $email = V::email($this->request->input('email'));
        $days = V::integer($this->request->input('expiresInDays', 7), 'expiresInDays', 1, 30);
        if ($this->request->input('role', 'moderator') !== 'moderator') V::fail('role');
        $message = V::text($this->request->input('message', ''), 'message', 0, 2000);
        return $this->db()->transaction(function () use ($email, $days, $message) {
            // Allow at most one active invitation for the same normalized
            // email.
            $this->db()->lock('invitation:' . $email);
            $query = 'SELECT EXISTS(SELECT 1 FROM accounts WHERE lower(email)=:email AND access_level>0)';
            if ($this->db()->value($query, ['email' => $email])) throw new ApiException(409, 'INVALID_STATE');

            $query = "UPDATE auth_tokens SET consumed_at=now() WHERE purpose='invitation' ";
            $query .= 'AND lower(email)=:email AND expires_at<=now() AND consumed_at IS NULL';
            $this->db()->query($query, ['email' => $email]);
            $query = "SELECT EXISTS(SELECT 1 FROM auth_tokens WHERE purpose='invitation' ";
            $query .= 'AND lower(email)=:email AND consumed_at IS NULL)';
            if ($this->db()->value($query, ['email' => $email])) throw new ApiException(409, 'INVALID_STATE');

            $token = bin2hex(random_bytes(32));
            $query = 'INSERT INTO auth_tokens(purpose,email,secret_hash,expires_at,data) ';
            $query .= "VALUES ('invitation',:email,decode(:hash,'hex'),now()+make_interval(days=>:days),";
            $query .= 'CAST(:data AS jsonb)) RETURNING *';
            $row = $this->row($query, [
                'email' => $email, 'hash' => hash_hmac('sha256', $token, $this->app->config['secret']),
                'days' => $days, 'data' => $this->encode(['invitedBy' => $this->user_id(), 'accessLevel' => 1]),
            ]);
            (new NotificationService($this->app))->email(
                $email, 'Moderator invitation',
                $message . "\n" . $this->app->config['origin'] . '/register?invitationToken=' . $token,
                'invite:' . $row['id']
            );
            return [
                'invitationId' => (int)$row['id'], 'email' => $email, 'role' => 'moderator',
                'status' => 'pending', 'expiresAt' => $this->date($row['expires_at']),
            ];
        });
    }

    /**
     * Consume a matching unexpired invitation before promoting the
     * authenticated account.
     */
    public function accept_invitation(): array {
        $token = V::text($this->request->input('invitationToken'), 'invitationToken', 64, 64);
        return $this->db()->transaction(function () use ($token) {
            $query = "UPDATE auth_tokens SET consumed_at=now() WHERE purpose='invitation' ";
            $query .= "AND secret_hash=decode(:hash,'hex') AND lower(email)=lower(:email) ";
            $query .= 'AND consumed_at IS NULL AND expires_at>now() RETURNING id';
            $invite = $this->db()->one($query, [
                'hash' => hash_hmac('sha256', $token, $this->app->config['secret']),
                'email' => $this->app->user['email'],
            ]);
            if (!$invite) throw new ApiException(410, 'INVITATION_EXPIRED');
            $query = 'UPDATE accounts SET access_level=greatest(access_level,1) WHERE id=:id';
            $this->db()->query($query, ['id' => $this->user_id()]);
            $this->audit('roleChange', 'account', $this->user_id(), ['invitationId' => (int)$invite['id']]);
            return ['accepted' => true];
        });
    }

    // =========================================================================
    // ADMINISTRATION COUNTS
    // =========================================================================

    public function counts(): array {
        return [
            'queue' => (int)$this->db()->value("SELECT count(*) FROM submissions WHERE status='pending'"),
            'reports' => (int)$this->db()->value('SELECT count(*) FROM reports WHERE closed_at IS NULL'),
            'accounts' => (int)$this->db()->value('SELECT count(*) FROM accounts WHERE verified_at IS NOT NULL'),
        ];
    }
}
