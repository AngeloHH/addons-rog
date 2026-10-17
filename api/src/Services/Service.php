<?php

/**
 * Addons ROG - Shared service helpers
 *
 * - Share request context, database access, and response value conversion.
 * - Provide audit events, pagination, configuration, and serialized rate limits.
 */

declare(strict_types=1);

namespace Rog\Services;

use DateTimeImmutable;
use DateTimeZone;
use Rog\App;
use Rog\Database\Db;
use Rog\Http\{Request, ApiException, Validator as V, Response};

abstract class Service {

    // =========================================================================
    // REQUEST CONTEXT AND IDENTITY
    // =========================================================================

    protected Request $request;

    public function __construct(protected App $app) { $this->request = $app->request; }
    protected function db(): Db { return $this->app->db(); }
    protected function user_id(): int { return (int)($this->app->user['id'] ?? 0); }
    protected function staff(): bool { return ($this->app->user['access_level'] ?? 0) >= 1; }
    protected function id(string $name): int { return V::integer($this->request->param($name), $name); }

    // =========================================================================
    // VALUE CONVERSION AND ROW LOOKUP
    // =========================================================================

    protected function json(mixed $value): array {
        return is_string($value) ? (json_decode($value, true, 512, JSON_THROW_ON_ERROR) ?? []) : (array)$value;
    }

    protected function encode(mixed $value): string {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    protected function date(?string $date): ?string {
        return $date === null ? null
            : (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    protected function external_id(mixed $id): ?int { return $id === null ? null : (int)$id; }

    /**
     * Require one result or raise the shared resource-not-found error.
     */
    protected function row(string $sql, array $params = []): array {
        return $this->db()->one($sql, $params) ?? throw new ApiException(404, 'NOT_FOUND');
    }

    // =========================================================================
    // STORED SETTINGS AND AUDIT EVENTS
    // =========================================================================

    protected function config(string $key, array $default = []): array {
        $value = $this->db()->value('SELECT value::text FROM settings WHERE key=:key', ['key' => $key]);
        return $value === false ? $default : $this->json($value);
    }

    protected function audit(string $kind, string $target, int|string|null $id, array $data = []): int {
        $actor = $this->app->user;
        $data += [
            'actorName' => $actor['username'] ?? 'system', 'actorRole' => $actor['role'] ?? 'system',
            'targetKind' => $target, 'targetId' => $id,
        ];
        $query = 'INSERT INTO events(kind,actor_id,data) VALUES (:kind,:actor,CAST(:data AS jsonb)) RETURNING id';
        return (int)$this->db()->value($query, [
            'kind' => $kind, 'actor' => $actor['id'] ?? null, 'data' => $this->encode((object)$data),
        ]);
    }

    /**
     * Expose a compact public identity without private account fields.
     */
    protected function user_ref(int|string|null $id): ?array {
        if ($id === null) return null;
        $row = $this->db()->one('SELECT id,username,profile FROM accounts WHERE id=:id', ['id' => $id]);
        if ($row === null) return null;
        $profile = $this->json($row['profile']);
        return [
            'id' => $this->external_id($row['id']), 'name' => $row['username'], 'username' => $row['username'],
            'color' => $profile['color'] ?? '#75849b',
        ];
    }

    // =========================================================================
    // PAGINATION AND EXPANSION VALUES
    // =========================================================================

    protected function page(array $items, int $total, int $offset, int $limit, array $extra = []): Response {
        $page = [
            'number' => (int)floor($offset / $limit) + 1, 'size' => $limit,
            'total' => $total, 'totalPages' => (int)ceil($total / $limit),
        ];
        return Response::ok($this->request, ['items' => $items] + $extra, 200, $page);
    }

    protected function pagination(int $default = 10, int $max = 100): array {
        $query = $this->request->query;
        $limit = V::integer($query['limit'] ?? $query['pageSize'] ?? $default, 'limit', 1, $max);
        $offset = (V::integer($query['page'] ?? 1, 'page') - 1) * $limit;
        // A valid cursor overrides the page-derived offset.
        if (isset($query['cursor'])) {
            $decoded = is_string($query['cursor']) ? base64_decode($query['cursor'], true) : false;
            if ($decoded === false || !ctype_digit($decoded)) V::fail('cursor');
            $offset = V::integer($decoded, 'cursor', 0, 1000000);
        }
        return [$limit, $offset];
    }

    protected function expansion(mixed $value): string {
        return V::choice($value, 'expansionId', ['wotlk', 'mop', 'wod', 'bfa', 'sl', 'df', 'tww']);
    }

    // =========================================================================
    // RATE LIMIT WINDOWS
    // =========================================================================

    protected function rate(string $name, int $limit, int $seconds, bool $record = true): void {
        $ip = $this->request->ip($this->app->config['trusted_proxies']);
        $pattern = '/^(upload|comment|request|report|staff-mail|verification-mail):[0-9]+$/D';
        // Account-scoped actions share a bucket across addresses; other limits use the client IP.
        $scope = preg_match($pattern, $name) ? 'account' : $ip;
        $key = hash_hmac('sha256', $name . ':' . $scope, $this->app->config['secret']);
        $this->db()->transaction(function () use ($key, $limit, $seconds, $record) {
            // Check and record the bucket under one lock so concurrent requests cannot bypass it.
            $this->db()->lock('rate:' . $key);
            $query = "SELECT count(*) FROM events WHERE kind='rateLimit' AND data->>'key'=:key ";
            $query .= 'AND occurred_at>now()-make_interval(secs=>:seconds)';
            $count = $this->db()->value($query, ['key' => $key, 'seconds' => $seconds]);
            if ($count >= $limit) throw new ApiException(429, 'RATE_LIMITED', [], [], $seconds);
            if ($record) $this->db()->query(
                "INSERT INTO events(kind,data) VALUES ('rateLimit',jsonb_build_object('key',CAST(:key AS text)))",
                ['key' => $key]
            );
        });
    }
}
