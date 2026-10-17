<?php

/**
 * Addons ROG - Stateful integration HTTP client
 *
 * - Send JSON or multipart requests while retaining cookies and CSRF tokens.
 * - Capture response bytes and headers and assert the expected HTTP status.
 */

class Client {
    public array $cookies = []; public string $csrf = ''; public array $last = [];

    /**
     * Keep redirects visible so download targets and intermediate statuses can
     * be tested.
     */
    public function call(
        string $method, string $path, ?array $body = null, int $expected = 200,
        ?array $file = null
    ): array {
        global $config;
        $cookies = array_map(fn($k, $v) => $k . '=' . $v, array_keys($this->cookies), array_values($this->cookies));
        $headers = [
            'Origin: ' . $config['origin'], 'X-CSRF-Token: ' . $this->csrf,
            'Cookie: ' . implode('; ', $cookies),
        ];

        // =====================================================================
        // REQUEST BODY ENCODING
        // =====================================================================

        $content = '';
        if ($file !== null) {
            $boundary = 'rog' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
            foreach ($body ?? [] as $key => $value) {
                $content .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
            }
            $content .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . ($file['field'] ?? 'file');
            $content .= "\"; filename=\"" . $file['name'] . "\"\r\nContent-Type: " . $file['type'] . "\r\n\r\n";
            $content .= $file['bytes'] . "\r\n--$boundary--\r\n";
        } elseif ($body !== null) {
            $content = json_encode((object)$body, JSON_THROW_ON_ERROR); $headers[] = 'Content-Type: application/json';
        }

        // =====================================================================
        // HTTP REQUEST AND RESPONSE STATE
        // =====================================================================

        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers),
            'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30,
        ]]);
        $raw = file_get_contents($config['origin'] . $path, false, $context);
        $response_headers = $http_response_header ?? [];
        preg_match('/HTTP\/\S+ (\d+)/', $response_headers[0] ?? '', $match); $status = (int)($match[1] ?? 0);
        foreach ($response_headers as $header) {
            if (
                preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $cookie)
            ) $this->cookies[$cookie[1]] = $cookie[2];
        }
        // Preserve binary/raw responses in last while returning decoded JSON
        // when available.
        $json = json_decode($raw ?: '', true); $data = $json;
        if (isset($data['csrfToken'])) $this->csrf = $data['csrfToken'];
        $this->last = ['status' => $status, 'body' => $json, 'headers' => $response_headers, 'raw' => $raw];
        $label = $method . ' ' . $path . ' expected ' . $expected . ' got ' . $status;
        check($status === $expected, $label . ($status !== $expected ? ' ' . substr($raw ?: '', 0, 400) : ''));
        return $data ?? [];
    }
}
