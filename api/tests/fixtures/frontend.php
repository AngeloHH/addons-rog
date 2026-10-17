<?php

/**
 * Addons ROG - Frontend integration fixtures
 *
 * - Prepare disposable SQLite accounts and valid image/ZIP files for browser tests.
 * - Expose test-only helpers for verification codes, invitations, cooldowns, and pack setup.
 */

declare(strict_types=1);

// =============================================================================
// DISPOSABLE SQLITE GUARD
// =============================================================================

$config = require dirname(__DIR__, 2) . '/bootstrap.php';
if (
    getenv('ROG_TEST_MODE') !== 'disposable' || $config['db_user'] !== 'rog_test'
        || !str_starts_with($config['dsn'], 'sqlite:')
) throw new RuntimeException('Disposable SQLite required');
$db = new Rog\Database\Db($config);

// =============================================================================
// BROWSER TEST SUPPORT COMMANDS
// =============================================================================

if (($argv[1] ?? '') === 'code') {
    $data = $db->value("SELECT data FROM messages WHERE data->>'dedupeKey'=:key", ['key' => 'verify:' . $argv[2]]);
    $app = new Rog\App($config); $app->request = new Rog\Http\Request('GET', '/');
    $mail = (new Rog\Services\NotificationService($app))->decode(json_decode($data, true));
    preg_match('/code is: ([A-Z0-9]{6})/', $mail['body'], $match);
    echo $match[1];
    exit;
}
if (($argv[1] ?? '') === 'invitation') {
    $data = $db->value("SELECT data FROM messages WHERE data->>'dedupeKey'=:key", ['key' => 'invite:' . $argv[2]]);
    $app = new Rog\App($config); $app->request = new Rog\Http\Request('GET', '/');
    $mail = (new Rog\Services\NotificationService($app))->decode(json_decode($data, true));
    preg_match('/invitationToken=([a-f0-9]{64})/', $mail['body'], $match);
    echo $match[1];
    exit;
}
if (($argv[1] ?? '') === 'resend-ready') {
    $token = $db->one(
        "SELECT * FROM auth_tokens WHERE purpose='verification' AND public_id::text=:id", ['id' => $argv[2]]
    );
    $data = json_decode($token['data'], true); $data['resendAvailableAt'] = gmdate('c', time() - 10);
    $db->query(
        'UPDATE auth_tokens SET data=CAST(:data AS jsonb) WHERE id=:id',
        ['data' => json_encode($data), 'id' => $token['id']]
    );
    exit;
}
if (($argv[1] ?? '') === 'pack') {
    $db->query(
        "INSERT INTO settings(key,value) VALUES ('packDefinitions',CAST(:data AS jsonb))",
        [
            'data' => json_encode(
                ['tank' => ['name' => 'Browser pack', 'membersByExpansion' => ['wotlk' => ['browser-addon']]]]
            )
        ]
    );
    exit;
}

// =============================================================================
// BINARY FILES AND VERIFIED ACCOUNTS
// =============================================================================

function png_fixture(int $width, int $height): string {
    $chunk = fn($type, $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $raw = str_repeat("\0" . str_repeat("\x70\x80\x90", $width), $height);
    return "\x89PNG\r\n\x1a\n" . $chunk(
        'IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)
    ) . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', '');
}
file_put_contents(getenv('ROG_STORAGE') . '/icon.png', png_fixture(256, 256));
file_put_contents(getenv('ROG_STORAGE') . '/screenshot.png', png_fixture(640, 360));
// Create predictable identities only inside the explicitly disposable browser
// test database.
foreach (['TestAuthor' => 0, 'TestPlayer' => 0, 'TestFounder' => 2] as $name => $level) $db->query(
    'INSERT INTO accounts(username,email,password_hash,verified_at,access_level) '
        . 'VALUES (:name,:email,:hash,now(),:level)',
    [
        'name' => $name, 'email' => strtolower($name) . '@example.test',
        'hash' => Rog\Support\Passwords::hash('Frontend test password 123'), 'level' => $level
    ]
);
$zip = new ZipArchive(); $zip->open(getenv('ROG_STORAGE') . '/test-addon.zip', ZipArchive::CREATE);
$zip->addFromString('BrowserAddon/BrowserAddon.toc', "## Interface: 30300\n## Title: BrowserAddon\nmain.lua\n");
$zip->addFromString('BrowserAddon/main.lua', 'local version=1'); $zip->close();
echo "Disposable frontend fixtures created";
