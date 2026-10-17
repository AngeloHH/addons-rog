<?php

/**
 * Addons ROG - Shared integration fixtures
 *
 * - Create verified accounts, application contexts, ZIP archives, and PNG images.
 * - Read verification codes from the encrypted disposable notification queue.
 */

// =============================================================================
// ACCOUNT VERIFICATION FIXTURES
// =============================================================================

function code_for(string $verification): string {
    global $db, $config;
    $app = new Rog\App($config); $app->request = new Rog\Http\Request('GET', '/');
    $data = $db->value("SELECT data FROM messages WHERE data->>'dedupeKey'=:key", ['key' => 'verify:' . $verification]);
    $mail = (new Rog\Services\NotificationService($app))->decode(json_decode($data, true));
    preg_match('/code is: ([A-Z0-9]{6})/', $mail['body'], $match);
    return $match[1];
}

/**
 * Exercise a rejected code and optionally resend it before completing account
 * verification.
 */
function register(Client $client, string $name, bool $resend = false): array {
    $client->call('GET', '/api/auth/session');
    $pending = $client->call(
        'POST', '/api/auth/register',
        [
            'username' => $name, 'email' => strtolower($name) . '@example.test', 'password' => 'A secure password 123',
            'termsAccepted' => true, 'expansionId' => 'wotlk'
        ],
        201
    );
    $client->call(
        'POST', '/api/auth/verify', ['verificationId' => $pending['verificationId'], 'code' => '000000'], 422
    );
    if ($resend) {
        global $db;
        $token = $db->one('SELECT * FROM auth_tokens WHERE public_id::text=:id', [
            'id' => $pending['verificationId'],
        ]);
        $data = json_decode($token['data'], true); $data['resendAvailableAt'] = gmdate('c', time() - 10);
        $db->query('UPDATE auth_tokens SET data=CAST(:data AS jsonb) WHERE id=:id', [
            'data' => json_encode($data), 'id' => $token['id'],
        ]);
        $renewed = $client->call('POST', '/api/auth/verify/resend', ['verificationId' => $pending['verificationId']]);
        check($renewed['verificationId'] !== $pending['verificationId'], 'Resending rotates the verification ID');
        $pending = $renewed;
    }
    $result = $client->call(
        'POST', '/api/auth/verify',
        ['verificationId' => $pending['verificationId'], 'code' => code_for($pending['verificationId'])]
    );
    return $result['user'];
}

// =============================================================================
// SERVICE CONTEXT AND BINARY FILE FIXTURES
// =============================================================================

function test_app(): Rog\App {
    global $config;
    $app = new Rog\App($config); $app->request = new Rog\Http\Request('GET', '/');
    return $app;
}

function archive_fixture(array $entries): string {
    $file = tempnam(sys_get_temp_dir(), 'rog'); $z = new ZipArchive();
    $z->open($file, ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) $z->addFromString($name, $content);
    $z->close(); $data = file_get_contents($file);
    unlink($file);
    return $data;
}

/**
 * Generate a valid RGB PNG at the requested dimensions without an image-library
 * dependency.
 */
function png_fixture(int $width, int $height): string {
    $chunk = fn($type, $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $raw = str_repeat("\0" . str_repeat("\x70\x80\x90", $width), $height);
    return "\x89PNG\r\n\x1a\n" . $chunk(
        'IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)
    ) . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', '');
}
