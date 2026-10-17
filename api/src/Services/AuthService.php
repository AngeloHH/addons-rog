<?php

/**
 * Addons ROG - Authentication and sessions
 *
 * - Manage account registration, email verification, and expiring sessions.
 * - Enforce access levels, signed CSRF tokens, and authentication rate limits.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{ApiException, Validator as V, Response};
use Rog\Support\Passwords;

class AuthService extends Service {

    // =========================================================================
    // SECRET HASHES, COOKIES, AND SESSION LOADING
    // =========================================================================

    private function hash(string $secret): string { return hash_hmac('sha256', $secret, $this->app->config['secret']); }

    private function cookie(string $name, string $value, int $expires = 0): void {
        $this->app->cookies[] = [$name, $value, [
            'expires' => $expires, 'path' => '/', 'secure' => $this->app->config['secure_cookie'],
            'httponly' => true, 'samesite' => 'Lax',
        ]];
    }

    /**
     * Load an active session, extend its lifetime when needed, and throttle presence updates.
     */
    public function load_session(): void {
        $secret = $this->request->cookies['rog_session'] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) return;
        $query = "SELECT * FROM auth_tokens WHERE purpose='session' AND secret_hash=decode(:hash,'hex') ";
        $query .= 'AND consumed_at IS NULL AND expires_at>now()';
        $token = $this->db()->one($query, ['hash' => $this->hash($secret)]);
        if ($token === null) return;

        $user = $this->row('SELECT * FROM account_state WHERE id=:id', ['id' => $token['account_id']]);
        if ($user['status'] === 'banned') { $this->cookie('rog_session', '', time() - 3600); return; }
        $this->app->user = $user; $this->app->session = $token;
        $remember = $this->json($token['data'])['remember'] ?? false;

        $query = 'UPDATE auth_tokens SET expires_at=now()+CAST(:duration AS interval) ';
        $query .= 'WHERE id=:id AND expires_at<now()+CAST(:threshold AS interval)';
        $this->db()->query($query, [
            'duration' => $remember ? '30 days' : '12 hours',
            'threshold' => $remember ? '29 days' : '11 hours', 'id' => $token['id'],
        ]);
        $query = 'UPDATE accounts SET last_seen_at=now() WHERE id=:id ';
        $query .= "AND (last_seen_at IS NULL OR last_seen_at<now()-interval '1 minute') RETURNING id";
        $seen = $this->db()->one($query, ['id' => $user['id']]);
        if ($seen) $this->db()->query(
            "INSERT INTO events(kind,actor_id) VALUES ('presence',:id)", ['id' => $user['id']]
        );
        if ($remember) $this->cookie('rog_session', $secret, time() + 2592000);
    }

    // =========================================================================
    // ACCESS RULES AND CSRF PROTECTION
    // =========================================================================

    public function authorize(string $level): void {
        if ($level === 'public') return;
        if ($this->app->user === null) throw new ApiException(401, 'UNAUTHENTICATED');
        if ($this->app->user['verified_at'] === null) throw new ApiException(403, 'EMAIL_NOT_VERIFIED');
        if (
            $level === 'uploader' && $this->app->user['status'] !== 'active'
        ) throw new ApiException(403, 'ACCOUNT_SUSPENDED');
        if ($level === 'staff' && !$this->staff()) throw new ApiException(403, 'FORBIDDEN');
        if (
            $level === 'founder' && (int)$this->app->user['access_level'] !== 2
        ) throw new ApiException(403, 'FORBIDDEN');
    }

    private function csrf_cookie(): string {
        $value = $this->request->cookies['rog_csrf'] ?? ''; $parts = explode('.', $value);
        if (
            count($parts) === 2 && preg_match('/^[a-f0-9]{48}$/D', $parts[0])
            && hash_equals($this->hash('csrf-cookie:' . $parts[0]), $parts[1])
        ) return $value;

        $nonce = bin2hex(random_bytes(24)); $value = $nonce . '.' . $this->hash('csrf-cookie:' . $nonce);
        $this->cookie('rog_csrf', $value); $this->request->cookies['rog_csrf'] = $value;
        return $value;
    }

    /**
     * Bind the signed CSRF cookie to the current session secret.
     */
    public function csrf_token(): string {
        return $this->hash('csrf-token:' . $this->csrf_cookie() . ':' . ($this->request->cookies['rog_session'] ?? ''));
    }

    public function check_csrf(): void {
        if (
            ($this->request->headers['origin'] ?? '') !== $this->app->config['origin']
        ) throw new ApiException(403, 'FORBIDDEN');
        $token = $this->request->headers['x-csrf-token'] ?? '';
        if (!is_string($token) || !hash_equals($this->csrf_token(), $token)) throw new ApiException(403, 'FORBIDDEN');
    }

    // =========================================================================
    // SESSION RESPONSE VALUES
    // =========================================================================

    public function session_user(?array $user): ?array {
        if ($user === null) return null;
        $profile = $this->json($user['profile']);
        return [
            'id' => $this->external_id($user['id']), 'name' => $user['username'], 'username' => $user['username'],
            'email' => $user['email'], 'color' => $profile['color'] ?? '#75849b',
            'role' => $user['role'], 'status' => $user['status'],
            'suspendedUntil' => $this->date($user['suspended_until']), 'expansionId' => $profile['expansionId'] ?? null,
        ];
    }

    public function me(): array {
        return [
            'authenticated' => $this->app->user !== null,
            'user' => $this->session_user($this->app->user), 'csrfToken' => $this->csrf_token(),
        ];
    }

    // =========================================================================
    // REGISTRATION AND VERIFICATION MESSAGES
    // =========================================================================

    public function register(): array {
        if ($this->app->user !== null) throw new ApiException(409, 'INVALID_STATE');
        $this->rate('register', 10, 3600); $body = $this->request->body;
        $name = V::text($body['username'] ?? null, 'username', 3, 24);
        if (!preg_match('/^[A-Za-z0-9_^]+$/D', $name)) V::fail('username');

        $email = V::email($body['email'] ?? null); $password = V::text($body['password'] ?? null, 'password', 8, 128);
        if (($body['termsAccepted'] ?? null) !== true) V::fail('termsAccepted');
        $expansion = $body['expansionId'] ?? $body['expansion'] ?? null;
        if ($expansion !== null) $this->expansion($expansion);
        return $this->db()->transaction(function () use ($name, $email, $password, $expansion, $body) {
            $invitation = null;
            // A supplied invitation must match the registration email before it can be linked.
            if (isset($body['invitationToken'])) {
                $raw = V::text($body['invitationToken'], 'invitationToken', 1, 256);
                $query = "SELECT id FROM auth_tokens WHERE purpose='invitation' AND lower(email)=:email ";
                $query .= "AND secret_hash=decode(:hash,'hex') AND consumed_at IS NULL AND expires_at>now() FOR UPDATE";
                $invitation = $this->db()->one($query, ['email' => $email, 'hash' => $this->hash($raw)]);
                if ($invitation === null) V::fail('invitationToken');
            }
            $profile = [
                'expansionId' => $expansion, 'color' => '#75849b',
                'terms' => ['version' => '2026-10', 'acceptedAt' => gmdate('c')],
            ];
            $query = 'INSERT INTO accounts(username,email,password_hash,profile) ';
            $query .= 'VALUES (:name,:email,:password,CAST(:profile AS jsonb)) RETURNING *';
            $user = $this->db()->one($query, [
                'name' => $name, 'email' => $email,
                'password' => Passwords::hash($password), 'profile' => $this->encode($profile),
            ]);
            $query = "INSERT INTO events(kind,actor_id,data) VALUES ('account',:id,CAST(:data AS jsonb))";
            $this->db()->query($query, ['id' => $user['id'], 'data' => $this->encode([
                'actorName' => $name, 'actorRole' => 'player', 'targetKind' => 'account',
                'targetId' => (int)$user['id'], 'targetLabel' => $name,
            ])]);
            return $this->verification($user, $invitation['id'] ?? null);
        });
    }

    /**
     * Invalidate older verification codes and queue a new code with a resend cooldown.
     */
    private function verification(array $user, ?int $invitation = null): array {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];

        $query = "UPDATE auth_tokens SET consumed_at=now() WHERE account_id=:id ";
        $query .= "AND purpose='verification' AND consumed_at IS NULL";
        $this->db()->query($query, ['id' => $user['id']]);
        $data = [
            'failedAttempts' => 0, 'resendAvailableAt' => gmdate('c', time() + 60),
            'emailAtIssue' => $user['email'], 'invitationId' => $invitation,
        ];
        $query = 'INSERT INTO auth_tokens(purpose,account_id,secret_hash,expires_at,data) ';
        $query .= "VALUES ('verification',:id,decode(:hash,'hex'),now()+interval '15 minutes',";
        $query .= 'CAST(:data AS jsonb)) RETURNING *';
        $token = $this->db()->one($query, [
            'id' => $user['id'], 'hash' => $this->hash($code), 'data' => $this->encode($data),
        ]);
        (new NotificationService($this->app))->email(
            $user['email'], 'Verify your account',
            'Your verification code is: ' . $code, 'verify:' . $token['public_id']
        );
        return [
            'verificationId' => $token['public_id'],
            'maskedEmail' => substr($user['email'], 0, 1) . '***@' . explode('@', $user['email'])[1],
            'expiresAt' => $this->date($token['expires_at']),
            'resendAvailableAt' => $this->date($data['resendAvailableAt']),
        ];
    }

    /**
     * Persist only the session secret hash and issue the raw value in an HTTP-only cookie.
     */
    private function new_session(array $user, bool $remember): array {
        $secret = bin2hex(random_bytes(32));
        $query = 'INSERT INTO auth_tokens(purpose,account_id,secret_hash,expires_at,data) ';
        $query .= "VALUES ('session',:id,decode(:hash,'hex'),now()+CAST(:duration AS interval),CAST(:data AS jsonb))";
        $this->db()->query($query, [
            'id' => $user['id'], 'hash' => $this->hash($secret), 'duration' => $remember ? '30 days' : '12 hours',
            'data' => $this->encode(['remember' => $remember]),
        ]);
        $this->db()->query('UPDATE accounts SET last_seen_at=now() WHERE id=:id', ['id' => $user['id']]);
        $this->db()->query("INSERT INTO events(kind,actor_id) VALUES ('presence',:id)", ['id' => $user['id']]);
        $this->request->cookies['rog_session'] = $secret;
        $this->cookie('rog_session', $secret, $remember ? time() + 2592000 : 0);
        $this->app->user = $this->row('SELECT * FROM account_state WHERE id=:id', ['id' => $user['id']]);
        return [
            'authenticated' => true, 'verified' => true,
            'user' => $this->session_user($this->app->user), 'csrfToken' => $this->csrf_token(),
        ];
    }

    // =========================================================================
    // CODE VERIFICATION AND RESEND
    // =========================================================================

    public function verify(): array {
        $id = V::text($this->request->input('verificationId'), 'verificationId', 36, 36);
        $code = strtoupper(V::text($this->request->input('code'), 'code', 6, 6));
        $result = $this->db()->transaction(function () use ($id, $code) {
            $query = "SELECT * FROM auth_tokens WHERE public_id::text=:id AND purpose='verification' FOR UPDATE";
            $token = $this->row($query, ['id' => $id]); $data = $this->json($token['data']);
            if (
                $token['consumed_at'] !== null || strtotime($token['expires_at']) <= time()
            ) return new ApiException(410, 'VERIFICATION_EXPIRED');
            $query = "SELECT encode(secret_hash,'hex') FROM auth_tokens WHERE id=:id";
            $hash = $this->db()->value($query, ['id' => $token['id']]);
            if (!hash_equals($hash, $this->hash($code))) {
                $data['failedAttempts'] = ($data['failedAttempts'] ?? 0) + 1;
                $query = 'UPDATE auth_tokens SET data=CAST(:data AS jsonb),';
                $query .= 'consumed_at=CASE WHEN :attempts>=5 THEN now() ELSE NULL END WHERE id=:id';
                $this->db()->query($query, [
                    'data' => $this->encode($data), 'attempts' => $data['failedAttempts'], 'id' => $token['id'],
                ]);
                // Return the failure so the transaction commits the attempt counter before the error is thrown.
                return new ApiException(422, 'INVALID_CODE', ['code' => ['INVALID_CODE']]);
            }
            $user = $this->row('SELECT * FROM accounts WHERE id=:id FOR UPDATE', ['id' => $token['account_id']]);
            if ($user['email'] !== $data['emailAtIssue']) return new ApiException(410, 'VERIFICATION_EXPIRED');
            $level = (int)$user['access_level'];
            // Consume an eligible invitation during verification before granting moderator access.
            if (!empty($data['invitationId'])) {
                $query = "UPDATE auth_tokens SET consumed_at=now(),";
                $query .= "data=jsonb_set(data::jsonb,'{acceptedBy}',to_jsonb(CAST(:user AS bigint))) ";
                $query .= "WHERE id=:id AND purpose='invitation' AND consumed_at IS NULL ";
                $query .= 'AND expires_at>now() AND lower(email)=lower(:email) RETURNING id';
                $invite = $this->db()->one($query, [
                    'user' => $user['id'], 'id' => $data['invitationId'], 'email' => $user['email'],
                ]);
                if ($invite !== null) $level = max($level, 1);
            }
            $query = 'UPDATE accounts SET verified_at=COALESCE(verified_at,now()),access_level=:level WHERE id=:id';
            $this->db()->query($query, ['level' => $level, 'id' => $user['id']]);
            if ($level !== (int)$user['access_level']) $this->audit('roleChange', 'account', $user['id'], [
                'targetLabel' => $user['username'], 'before' => (int)$user['access_level'],
                'after' => $level, 'invitationId' => $data['invitationId'] ?? null,
            ]);
            $this->db()->query('UPDATE auth_tokens SET consumed_at=now() WHERE id=:id', ['id' => $token['id']]);
            return $this->new_session($user, false);
        });
        if ($result instanceof ApiException) throw $result;
        return $result;
    }

    public function resend(): array {
        $id = V::text($this->request->input('verificationId'), 'verificationId', 36, 36);
        return $this->db()->transaction(function () use ($id) {
            $query = "SELECT * FROM auth_tokens WHERE public_id::text=:id AND purpose='verification' FOR UPDATE";
            $token = $this->row($query, ['id' => $id]); $data = $this->json($token['data']);
            $user = $this->row('SELECT * FROM accounts WHERE id=:id FOR UPDATE', ['id' => $token['account_id']]);
            if ($user['verified_at'] !== null) throw new ApiException(409, 'INVALID_STATE');

            $query = "SELECT data->>'resendAvailableAt' FROM auth_tokens WHERE account_id=:id ";
            $query .= "AND purpose='verification' ORDER BY created_at DESC,id DESC LIMIT 1";
            $latest = $this->db()->value($query, ['id' => $user['id']]);
            if ($latest && strtotime($latest) > time()) throw new ApiException(429, 'RATE_LIMITED', [], [], 60);
            if (strtotime($data['resendAvailableAt']) > time()) throw new ApiException(429, 'RATE_LIMITED', [], [], 60);
            if (isset($this->request->body['email'])) {
                $user['email'] = V::email($this->request->body['email']);
                $this->db()->query('UPDATE accounts SET email=:email WHERE id=:id', [
                    'email' => $user['email'], 'id' => $user['id'],
                ]);
            }
            return $this->verification($user, $data['invitationId'] ?? null);
        });
    }

    // =========================================================================
    // LOGIN AND LOGOUT
    // =========================================================================

    public function login(): array {
        $identifier = V::text($this->request->input('identifier'), 'identifier', 1, 254);
        $password = V::text($this->request->input('password'), 'password', 1, 128);
        $remember = V::boolean($this->request->input('remember', false), 'remember');
        $this->rate('login:' . strtolower($identifier), 5, 900, false);

        $query = 'SELECT * FROM account_state WHERE lower(username)=lower(:name) OR lower(email)=lower(:email)';
        $user = $this->db()->one($query, ['name' => $identifier, 'email' => $identifier]);
        // Use a fallback hash so an unknown identity still performs password verification.
        $hash = $user['password_hash'] ?? 'sha384:$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (!Passwords::verify($password, $hash) || $user === null) {
            $this->rate('login:' . strtolower($identifier), 5, 900);
            throw new ApiException(401, 'INVALID_CREDENTIALS');
        }
        if ($user['status'] === 'banned') throw new ApiException(403, 'ACCOUNT_BANNED');
        if ($user['verified_at'] === null) {
            $this->rate('verification-mail:' . $user['id'], 1, 60);
            $details = $this->db()->transaction(fn() => $this->verification($user));
            throw new ApiException(403, 'EMAIL_NOT_VERIFIED', [], $details);
        }
        return $this->db()->transaction(fn() => $this->new_session($user, $remember));
    }

    /**
     * Consume the current session token and expire its browser cookie.
     */
    public function logout(): Response {
        if ($this->app->session !== null) $this->db()->query(
            'UPDATE auth_tokens SET consumed_at=now() WHERE id=:id', ['id' => $this->app->session['id']]
        );
        $this->cookie('rog_session', '', time() - 3600);
        return Response::ok($this->request, [], 200);
    }
}
