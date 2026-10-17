<?php

/**
 * Addons ROG - Web installer integration tests
 *
 * - Exercise setup authorization, connection probes, input validation, and founder creation.
 * - Inject filesystem failures and verify rollback, resumption, and permanent setup locking.
 */

declare(strict_types=1);

// =============================================================================
// ISOLATED ENVIRONMENT AND ASSERTIONS
// =============================================================================

require dirname(__DIR__) . '/support/results.php';
$config = require dirname(__DIR__, 2) . '/bootstrap.php'; $root = dirname(__DIR__, 2);
if (
    getenv('ROG_TEST_MODE') !== 'disposable' || $config['db_user'] !== 'rog_test'
        || !str_contains($root, 'installer-copy-')
) throw new RuntimeException('Installer tests require an isolated copy and disposable database');
use Rog\App;
use Rog\Http\{Request, ApiException};
use Rog\Services\InstallService;
use Rog\Database\Db;
$checks = [];
function installation_check(bool $value, string $name): void {
    global $checks;
    if (!$value) throw new RuntimeException('FAILED: ' . $name);
    $checks[] = $name;
}

function installation_error(callable $call, string $code): void {
    try { $call(); } catch (ApiException $error) {
        installation_check($error->error_code === $code, 'Rejected ' . $code);
        return;
    }
    installation_check(false, 'Expected ' . $code);
}

// =============================================================================
// INSTALLER HTTP CLIENT
// =============================================================================

class SetupClient {
    public array $cookies = [];
    public array $last = [];

    /**
     * Send setup credentials by default and allow individual headers to be
     * omitted for rejection tests.
     */
    public function call(
        string $method, string $path, ?array $body = null, int $expected = 200, array $extra = []
    ): array {
        $url = getenv('ROG_SETUP_TEST_URL');
        $headers = array_replace(
            ['Origin' => $url, 'X-Setup-Token' => getenv('ROG_SETUP_TEST_TOKEN')],
            $body === null ? [] : ['Content-Type' => 'application/json'], $extra
        );
        $lines = [];
        foreach ($headers as $name => $value) if ($value !== null) $lines[] = $name . ': ' . $value;
        if ($this->cookies) $lines[] = 'Cookie: ' . implode(
            '; ', array_map(fn($k, $v) => $k . '=' . $v, array_keys($this->cookies), array_values($this->cookies))
        );
        $context = stream_context_create(
            [
                'http' => [
                    'method' => $method, 'header' => implode("\r\n", $lines),
                    'content' => $body === null ? '' : json_encode((object)$body, JSON_THROW_ON_ERROR),
                    'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30
                ]
            ]
        );
        $raw = file_get_contents($url . $path, false, $context); $response_headers = $http_response_header ?? [];
        preg_match('/HTTP\/\S+ (\d+)/', $response_headers[0] ?? '', $match);
        $status = (int)($match[1] ?? 0);
        foreach ($response_headers as $header) if (
            preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $cookie)
        ) $this->cookies[$cookie[1]] = $cookie[2];
        $this->last = ['status' => $status, 'raw' => $raw, 'headers' => $response_headers];
        installation_check(
            $status === $expected, $method . ' ' . $path . ' status ' . $expected . ' (got ' . $status . ')'
        );
        $data = json_decode($raw ?: '', true, 512, JSON_THROW_ON_ERROR);
        if ($status !== $expected) throw new RuntimeException('Unexpected setup response');
        return $data;
    }
}

// =============================================================================
// DRIVER-SPECIFIC DATABASE FIXTURES
// =============================================================================

$driver = strstr($config['dsn'], ':', true);
// Give each installation/recovery case its own disposable database destination.
$fixture_config = function (string $name) use ($config, $driver): array {
    $dsn = $driver === 'sqlite' ? 'sqlite:' . dirname(substr($config['dsn'], 7)) . '/' . $name . '.sqlite'
        : preg_replace('/dbname=[^;]+/', 'dbname=' . $name, $config['dsn']);
    return array_replace($config, ['dsn' => $dsn, 'create_database' => true]);
};
if ($driver === 'sqlite') {
    $exists = fn(string $name) => is_file(substr($fixture_config($name)['dsn'], 7));
    $database = [
        'driver' => 'sqlite', 'path' => substr($fixture_config('rog_test_installer')['dsn'], 7),
        'createIfMissing' => true
    ];
} else {
    preg_match('/host=([^;]+);port=(\d+)/', $config['dsn'], $connection);
    $host = $connection[1]; $port = (int)$connection[2];
    $pdo = (new Db($config))->pdo;
    $exists = function (string $name) use ($pdo, $driver): bool {
        $query = $pdo->prepare(
            $driver === 'pgsql' ? 'SELECT EXISTS(SELECT 1 FROM pg_database WHERE datname=:name)'
                : 'SELECT EXISTS(SELECT 1 FROM information_schema.schemata WHERE schema_name=:name)'
        );
        $query->execute(['name' => $name]);
        return (bool)$query->fetchColumn();
    };
    $database = [
        'driver' => $driver, 'host' => $host, 'port' => $port, 'name' => 'rog_test_installer', 'username' => 'rog_test',
        'password' => $config['db_password'], 'createIfMissing' => true
    ] + ($driver === 'pgsql' ? ['sslMode' => 'disable'] : []);
}
$destination = function (array $database, string $name) use ($driver, $fixture_config): array {
    if ($driver === 'sqlite') $database['path'] = substr($fixture_config($name)['dsn'], 7);
    else $database['name'] = $name;
    return $database;
};
$payload = [
    'database' => $database,
    'site' => [
        'url' => getenv('ROG_SETUP_TEST_URL'), 'name' => "Angelo's test site", 'language' => 'es', 'timezone' => 'UTC'
    ],
    'admin' => [
        'username' => 'SetupFounder', 'email' => 'founder@example.test', 'password' => 'Setup passphrase 123!',
        'passwordConfirmation' => 'Setup passphrase 123!'
    ]
];

// =============================================================================
// SETUP TOKEN LIFETIME AND REQUEST AUTHORIZATION
// =============================================================================

$client = new SetupClient; $status = $client->call('GET', '/api/setup/status', extra: ['X-Setup-Token' => null]);
installation_check(
    $status['available'] && !$status['installed'], 'Installer works without application credentials or secret'
);
$token_file = $root . '/config/setup.token.json'; $token_contents = file_get_contents($token_file);
installation_check(
    !str_contains($token_contents, getenv('ROG_SETUP_TEST_TOKEN')), 'Only a hash of the temporary token is stored'
);
// Temporarily remove or expire the saved token, then restore it for authorized
// setup requests.
unlink($token_file);
$client->call('GET', '/api/setup/requirements', expected: 404); $expired = json_decode($token_contents, true);
$expired['expiresAt'] = time() - 1;
file_put_contents($token_file, json_encode($expired));
$client->call('GET', '/api/setup/requirements', expected: 404); $status = $client->call('GET', '/api/setup/status');
installation_check(!$status['available'], 'Expired token disables installation');
file_put_contents($token_file, $token_contents);
$client->call('GET', '/api/setup/requirements', expected: 403, extra: ['X-Setup-Token' => null]);
$client->call('GET', '/api/setup/requirements', expected: 403, extra: ['X-Setup-Token' => 'wrong']);
$requirements = $client->call('GET', '/api/setup/requirements');
installation_check(
    $requirements['ready'] && in_array($driver, array_column($requirements['database']['drivers'], 'id'), true),
    'Requirements come from the running PHP environment'
);
$client->call('POST', '/api/setup/database', ['database' => $database], 403, ['Origin' => 'https://other.example']);
$client->call('POST', '/api/setup/database', ['database' => $database], 403, ['Origin' => null]);
$bad = $database; $bad['driver'] = 'unsupported';
$client->call('POST', '/api/setup/database', ['database' => $bad], 422); $bad = $database;
$bad['host'] = '127.0.0.1;dbname=postgres'; $client->call('POST', '/api/setup/database', ['database' => $bad], 422);
if ($driver !== 'sqlite') {
    $bad = $database; $bad['password'] = 'incorrect-temporary-password';
    $result = $client->call('POST', '/api/setup/database', ['database' => $bad], 422);
    installation_check(
        $result['error']['code'] === 'DATABASE_CONNECTION_FAILED', 'Connection failure has a sanitized error'
    );
}

// =============================================================================
// DATABASE PREFLIGHT WITHOUT CREATION
// =============================================================================

$probe = $client->call('POST', '/api/setup/database', ['database' => $database]);
installation_check(
    $probe['ready'] && !$probe['databaseExists'], 'Connection test can report an absent database without creating it'
);
installation_check(!$exists('rog_test_installer'), 'Connection test leaves database absent');
$empty = $destination($database, 'rog_test_setup_empty');
$empty_db = new Db($fixture_config('rog_test_setup_empty'), createDatabase: true);
$probe = $client->call('POST', '/api/setup/database', ['database' => $empty]);
installation_check($probe['ready'] && $probe['schemaEmpty'], 'Existing empty database passes preflight');
installation_check(!$empty_db->has_table('accounts'), 'Existing database gets no tables during connection test');
$occupied = $destination($database, 'rog_test_app');
if ($driver === 'sqlite') $occupied['path'] = substr($config['dsn'], 7);
$probe = $client->call('POST', '/api/setup/database', ['database' => $occupied]);
installation_check(!$probe['ready'] && $probe['reason'] === 'DATABASE_NOT_EMPTY', 'Existing application is rejected');

// =============================================================================
// INVALID INSTALLATION INPUT
// =============================================================================

$bad = $payload; $bad['admin']['passwordConfirmation'] = 'mismatch';
$client->call('POST', '/api/setup/install', $bad, 422); $bad = $payload;
$bad['admin']['password'] = $bad['admin']['passwordConfirmation'] = 'short';
$client->call('POST', '/api/setup/install', $bad, 422); $bad = $payload;
$bad['site']['storagePath'] = $root . '/public/uploads'; $client->call('POST', '/api/setup/install', $bad, 422);
$bad = $payload; $bad['site']['registration'] = false;
$client->call('POST', '/api/setup/install', $bad, 422);
installation_check(
    !$exists('rog_test_installer') && !is_file($root . '/config/config.local.php'),
    'Invalid installation inputs create neither database nor configuration'
);

// =============================================================================
// EXCLUSIVE INSTALLATION GUARD
// =============================================================================

$guard = fopen($root . '/config/.install.guard', 'c');
flock($guard, LOCK_EX);
try {
    $result = $client->call('POST', '/api/setup/install', $payload, 409);
    installation_check(
        $result['error']['code'] === 'INSTALLATION_BUSY', 'Parallel installation is serialized across PHP processes'
    );
} finally {
    flock($guard, LOCK_UN);
    fclose($guard);
}

// =============================================================================
// SUCCESSFUL INSTALLATION AND FOUNDER LOGIN
// =============================================================================

$result = $client->call('POST', '/api/setup/install', $payload, 201);
installation_check(
    $result['installed'] && $result['locked'] && !$result['resumed'], 'Installation reports actual completion and lock'
);
installation_check(
    ($config['db_password'] === '' || !str_contains($client->last['raw'], $config['db_password']))
        && !str_contains($client->last['raw'], $payload['admin']['password']) && !str_contains(
            $client->last['raw'], getenv('ROG_SETUP_TEST_TOKEN')
        ),
    'Successful response contains no credentials'
);
// Inspect only the private configuration created inside this disposable
// installer copy.
$saved = require $root . '/config/config.local.php';
installation_check(
    $saved['db_password'] === $config['db_password'] && $saved['mail_transport'] === 'spool'
        && strlen($saved['secret']) === 64,
    'Private configuration is executable, complete, and has a generated secret'
);
$installed = new Db($saved);
installation_check(!array_key_exists('auto_schema', $saved), 'Private configuration has no automatic schema option');
installation_check(!$installed->has_table('schema_migrations'), 'Installer creates no migration history');
installation_check((int)$installed->value('SELECT count(*) FROM accounts') === 1, 'Exactly one initial account exists');
$founder = $installed->one('SELECT * FROM accounts');
installation_check(
    $founder['access_level'] === 2 && $founder['verified_at'] !== null && Rog\Support\Passwords::verify(
        $payload['admin']['password'], $founder['password_hash']
    ),
    'Founder is verified, level 2, and password hashed'
);
installation_check(
    $installed->value("SELECT value->>'name' FROM settings WHERE key='site'") === "Angelo's test site",
    'Site metadata persists with literal quotes'
);
installation_check(
    !is_file($root . '/config/setup.pending.json') && !is_file($root . '/config/setup.token.json')
        && is_file($root . '/config/install.lock'),
    'Temporary journal and token are removed after permanent lock'
);
$status = $client->call('GET', '/api/setup/status', extra: ['X-Setup-Token' => null]);
installation_check(
    $status['installed'] && !$status['available'], 'Status reports installer disabled after installation'
);
$client->call('POST', '/api/setup/install', $payload, 409);
$client->call('GET', '/api/setup/requirements', expected: 409);
$client->call('POST', '/api/setup/database', ['database' => $database], 409);
$session = $client->call('GET', '/api/auth/session');
$login = $client->call(
    'POST', '/api/auth/login', ['identifier' => 'SetupFounder', 'password' => $payload['admin']['password']],
    extra: ['X-CSRF-Token' => $session['csrfToken']]
);
installation_check($login['authenticated'], 'Founder can log in immediately without email verification');
$client->call('GET', '/api/admin/counts');

// =============================================================================
// FILESYSTEM FAILURE INJECTION AND RECOVERY
// =============================================================================

/**
 * Fail while staging configuration so the database transaction cannot commit
 * founder creation.
 */
class BeforeCommitInstall extends InstallService {

    protected function stage_config(array $journal): void {
        throw new RuntimeException('Simulated file failure before commit');
    }
}

/**
 * Fail after database commit so a retry must finalize the original
 * installation.
 */
class AfterCommitInstall extends InstallService {

    protected function publish_config(array $journal): void {
        throw new RuntimeException('Simulated file failure after commit');
    }
}
// Use separate source roots and database destinations for the two recovery
// boundaries.
foreach (['rollback' => BeforeCommitInstall::class, 'resume' => AfterCommitInstall::class] as $mode => $class) {
    $fixture = $root . '/tests/.runtime/' . $mode;
    foreach (['config', 'public', 'storage', 'database'] as $dir) mkdir($fixture . '/' . $dir, 0700, true);
    foreach (['models.php', 'domains.sql', 'objects.sql'] as $file) copy(
        $root . '/database/' . $file, $fixture . '/database/' . $file
    );
    $local = array_replace(
        $config,
        [
            'dsn' => '', 'secret' => '', 'setup_token' => getenv('ROG_SETUP_TEST_TOKEN'),
            'origin' => getenv('ROG_SETUP_TEST_URL')
        ]
    );
    $app = new App($local); $input = $payload;
    $input['database'] = $destination($database, 'rog_test_setup_' . $mode);
    $app->request = new Request(
        'POST', '/setup/install', body: $input,
        headers: ['Origin' => $local['origin'], 'X-Setup-Token' => $local['setup_token']]
    );
    $service = new $class($app, $fixture); $service->authorize();
    installation_error(
        fn() => $service->install(), $mode === 'rollback' ? 'INSTALL_FAILED' : 'INSTALL_FINALIZE_FAILED'
    );
    $journal = json_decode(file_get_contents($fixture . '/config/setup.pending.json'), true);
    if ($mode === 'resume') file_put_contents($fixture . '/config/install.lock', $journal['id'] . "\n");
    $inspect = new Db($journal['config'], false);
    if ($mode === 'rollback') installation_check(
        (
            $driver === 'mysql' ? (int)$inspect->value('SELECT count(*) FROM accounts') === 0
                : !$inspect->has_table('accounts')
        ),
        'Pre-commit file failure leaves no founder (MySQL retains resumable tables)'
    );
    else installation_check(
        (int)$inspect->value('SELECT count(*) FROM accounts') === 1,
        'Post-commit file failure preserves one recoverable founder'
    );
    installation_check(
        !is_file($fixture . '/config/config.local.php'), 'Failed publication does not expose partial configuration'
    );
    $app->request = new Request(
        'POST', '/setup/install', body: [],
        headers: ['Origin' => $local['origin'], 'X-Setup-Token' => $local['setup_token']]
    );
    $service = new InstallService($app, $fixture); $service->authorize();
    installation_check($service->status()['pending'], 'Status identifies a pending recovery');
    $result = $service->install();
    installation_check(
        $result['resumed'] && is_file($fixture . '/config/config.local.php'), 'Retry resumes the original installation'
    );
    installation_check(
        (int)$inspect->value('SELECT count(*) FROM accounts') === 1, 'Recovery never duplicates the founder'
    );
    installation_error(fn() => $service->authorize(), 'SETUP_LOCKED');
}

// =============================================================================
// PERMANENT INSTALLATION LOCK AND TEST REPORT
// =============================================================================

unlink($root . '/config/config.local.php');
$status = $client->call('GET', '/api/setup/status');
installation_check(
    $status['installed'] && !$status['available'], 'Removing configuration does not unlock installation'
);
$client->call('POST', '/api/setup/install', $payload, 409);
file_put_contents(
    test_result_path('installer-results-' . $driver . '.json'),
    json_encode(
        [
            'passed' => count($checks), 'checks' => $checks,
            'scope' => 'HTTP installation in an isolated copy; disposable database; no external email'
        ],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    )
);
echo count($checks), " installer checks passed\n";
