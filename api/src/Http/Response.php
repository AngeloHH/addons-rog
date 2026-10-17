<?php

/**
 * Addons ROG - HTTP response formatting
 *
 * - Build consistent success and error payloads, including retry metadata.
 * - Send JSON, raw content, or streamed files with the selected headers.
 */

declare(strict_types=1);

namespace Rog\Http;

class Response {

    // =========================================================================
    // RESPONSE STATE
    // =========================================================================

    public array $cookies = [];
    public ?string $file = null;
    public ?string $raw = null;

    public function __construct(public mixed $body = [], public int $status = 200, public array $headers = []) {}

    // =========================================================================
    // JSON RESPONSE ENVELOPES
    // =========================================================================

    public static function ok(Request $request, array $data = [], int $status = 200, ?array $page = null): self {
        $body = ['ok' => true] + $data;
        if ($page !== null) $body['page'] = $page;
        return new self($status === 204 ? null : $body, $status);
    }

    public static function error(Request $request, ApiException $error): self {
        $data = ['code' => $error->error_code, 'message' => $error->error_code]; $status = $error->status;
        if ($error->fields) $data['fields'] = $error->fields;
        if ($error->details) $data['details'] = $error->details;
        if ($error->retry_after !== null) $data['retryAfterSeconds'] = $error->retry_after;

        $data['requestId'] = $request->request_id;
        $response = new self(['ok' => false, 'error' => $data], $status);
        if ($error->retry_after !== null) $response->headers['Retry-After'] = (string)$error->retry_after;
        return $response;
    }

    // =========================================================================
    // FILE DOWNLOADS
    // =========================================================================

    public static function stream(string $file, string $name, string $type = 'application/zip'): self {
        if (!is_file($file)) throw new ApiException(404, 'NOT_FOUND');
        // Use a safe basename in the download header.
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name));
        $response = new self(null, 200, [
            'Content-Type' => $type, 'Content-Length' => (string)filesize($file),
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
        $response->file = $file; return $response;
    }

    // =========================================================================
    // HEADERS AND BODY OUTPUT
    // =========================================================================

    public function send(): void {
        http_response_code($this->status);
        $headers = $this->headers + [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store',
        ];
        foreach ($headers as $key => $value) header($key . ': ' . $value);
        foreach ($this->cookies as [$name, $value, $options]) setcookie($name, $value, $options);
        // File and raw responses bypass JSON serialization; status 204 has no JSON body.
        if ($this->file !== null) { readfile($this->file); return; }
        if ($this->raw !== null) { echo $this->raw; return; }
        if ($this->status !== 204) echo json_encode(
            $this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
