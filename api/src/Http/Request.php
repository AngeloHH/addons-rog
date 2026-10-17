<?php

/**
 * Addons ROG - HTTP request parsing
 *
 * - Normalize paths, headers, and request identifiers before routing.
 * - Validate request bodies and resolve client addresses through trusted proxies.
 */

declare(strict_types=1);

namespace Rog\Http;

use JsonException;
use stdClass;

class Request {

    // =========================================================================
    // REQUEST STATE AND NORMALIZATION
    // =========================================================================

    public array $params = [];
    public string $request_id;

    public function __construct(
        public string $method, public string $path, public array $query = [],
        public array $body = [], public array $headers = [], public array $cookies = [],
        public array $files = [], public string $remote_ip = '127.0.0.1'
    ) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->request_id = bin2hex(random_bytes(12)); $this->method = strtoupper($method);
        $this->path = preg_replace('~^/api(?=/|$)~', '', $path) ?: '/';
        $this->path = '/' . trim($this->path, '/');
    }

    // =========================================================================
    // BODY CAPTURE
    // =========================================================================

    public static function capture(array $config): self {
        $headers = function_exists('getallheaders') ? getallheaders() : []; $body = $_POST;
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 60 * 1024 * 1024) throw new ApiException(413, 'FILE_TOO_LARGE');

        $type = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        // JSON bodies must be objects and stay within the separate one MiB limit.
        if (str_contains($type, 'application/json')) {
            $raw = file_get_contents('php://input', false, null, 0, 1048577);
            if (strlen($raw) > 1048576) throw new ApiException(413, 'FILE_TOO_LARGE');
            try { $object = json_decode($raw, false, 64, JSON_THROW_ON_ERROR); }
            catch (JsonException) { throw new ApiException(400, 'BAD_REQUEST'); }
            if (!$object instanceof stdClass) throw new ApiException(400, 'BAD_REQUEST');
            $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } elseif (
            in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)
            && !str_starts_with($type, 'multipart/form-data') && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
        ) throw new ApiException(415, 'UNSUPPORTED_MEDIA_TYPE');

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return new self(
            $_SERVER['REQUEST_METHOD'], $path, $_GET, $body,
            $headers, $_COOKIE, $_FILES, $_SERVER['REMOTE_ADDR'] ?? ''
        );
    }

    // =========================================================================
    // REQUEST VALUES AND CLIENT ADDRESS
    // =========================================================================

    public function input(string $key, mixed $default = null): mixed { return $this->body[$key] ?? $default; }
    public function param(string $key): string {
        return $this->params[$key] ?? throw new ApiException(400, 'BAD_REQUEST');
    }

    public function ip(array $trusted): string {
        // Accept forwarded addresses only from an explicitly trusted direct peer.
        if (!in_array($this->remote_ip, $trusted, true)) return $this->remote_ip;
        $ip = trim(explode(',', $this->headers['x-forwarded-for'] ?? '')[0]);
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : $this->remote_ip;
    }
}
