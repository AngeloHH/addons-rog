<?php

/**
 * Addons ROG - Web installer
 *
 * - Validate setup authorization, runtime requirements, and database settings.
 * - Create the initial schema and founder with resumable private configuration.
 */

declare(strict_types=1);

namespace Rog\Services;

use DateTimeZone;
use PDO;
use PDOException;
use Rog\App;
use Rog\Database\{Db, Schema};
use Rog\Http\{ApiException, Validator as V};
use Rog\Models\{Account, Setting};
use Rog\Support\Passwords;
use RuntimeException;
use Throwable;

class InstallService extends Service {

    // =========================================================================
    // INSTALLER PATHS AND PERSISTED STATE
    // =========================================================================

    private string $root;

    public function __construct(App $app, ?string $root = null) {
        parent::__construct($app); $this->root = $root ?? dirname(__DIR__, 2);
    }

    private function path(string $file): string { return $this->root . '/config/' . $file; }

    private function pending(): ?array {
        if (!is_file($this->path('setup.pending.json'))) return null;
        return json_decode(file_get_contents($this->path('setup.pending.json')), true, 32, JSON_THROW_ON_ERROR);
    }

    /**
     * Treat configured deployments as installed while allowing the matching
     * pending journal to resume.
     */
    private function locked(): bool {
        clearstatcache();
        if (is_file($this->path('config.local.php')) || $this->app->config['dsn'] !== '') return true;
        if (!is_file($this->path('install.lock'))) return false;
        $pending = $this->pending();
        return $pending === null || trim(file_get_contents($this->path('install.lock'))) !== $pending['id'];
    }

    private function token_hash(): ?string {
        $token = $this->app->config['setup_token'] ?? '';
        if (strlen($token) >= 32 && strlen($token) <= 256) return hash('sha256', $token);
        if (!is_file($this->path('setup.token.json'))) return null;
        $saved = json_decode(file_get_contents($this->path('setup.token.json')), true, 8, JSON_THROW_ON_ERROR);
        return ($saved['expiresAt'] ?? 0) > time() ? $saved['hash'] : null;
    }

    // =========================================================================
    // SETUP STATUS AND AUTHORIZATION
    // =========================================================================

    public function status(): array {
        $locked = $this->locked(); $pending = is_file($this->path('setup.pending.json'));
        return [
            'installed' => $locked, 'available' => !$locked && $this->token_hash() !== null,
            'pending' => !$locked && $pending,
            'phase' => $locked ? 'installed' : ($pending ? 'pending' : 'unconfigured'),
        ];
    }

    public function authorize(): void {
        if ($this->request->method === 'GET' && $this->request->path === '/setup/status') return;
        if ($this->locked()) throw new ApiException(409, 'SETUP_LOCKED');
        $expected = $this->token_hash();
        if ($expected === null) throw new ApiException(404, 'SETUP_DISABLED');
        $token = $this->request->headers['x-setup-token'] ?? '';
        if (
            !is_string($token) || strlen($token) > 256 || !hash_equals($expected, hash('sha256', $token))
        ) throw new ApiException(403, 'SETUP_TOKEN_INVALID');
        if (
            $this->request->method === 'POST'
            && ($this->request->headers['origin'] ?? '') !== $this->app->config['origin']
        ) throw new ApiException(403, 'ORIGIN_NOT_ALLOWED');
    }

    /**
     * Issue a short-lived setup credential and persist only its hash.
     */
    public function issue_token(): array {
        return $this->guard(function () {
            if ($this->locked()) throw new ApiException(409, 'SETUP_LOCKED');
            $error = 'ROG_SETUP_TOKEN is already configured; use that token or unset it first';
            if (strlen($this->app->config['setup_token'] ?? '') >= 32) throw new RuntimeException($error);
            $token = bin2hex(random_bytes(32)); $expires = time() + 3600;
            $this->write_private($this->path('setup.token.json'), $this->encode([
                'hash' => hash('sha256', $token), 'expiresAt' => $expires,
            ]));
            return ['token' => $token, 'expiresAt' => gmdate('c', $expires)];
        });
    }

    // =========================================================================
    // EXCLUSIVE INSTALLATION AND PRIVATE FILES
    // =========================================================================

    /**
     * Reject concurrent installer mutations using a nonblocking filesystem
     * lock.
     */
    private function guard(callable $callback): mixed {
        $old = umask(0077);
        try { $handle = fopen($this->path('.install.guard'), 'c'); } finally { umask($old); }
        if ($handle === false) throw new ApiException(503, 'CONFIG_NOT_WRITABLE');
        if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); throw new ApiException(409, 'INSTALLATION_BUSY'); }
        try { return $callback(); } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    /**
     * Write, flush, and sync a private temporary file before atomically
     * publishing it.
     */
    protected function write_private(string $path, string $contents): void {
        $temp = $this->path('.setup-' . bin2hex(random_bytes(12))); $old = umask(0077);
        try { $file = fopen($temp, 'xb'); } finally { umask($old); }
        if ($file === false) throw new RuntimeException('Cannot create private installation file');
        try {
            $offset = 0;
            while ($offset < strlen($contents)) {
                $written = fwrite($file, substr($contents, $offset));
                if ($written === false || $written === 0) throw new RuntimeException('Cannot write installation file');
                $offset += $written;
            }
            if (!fflush($file) || !fsync($file)) throw new RuntimeException('Cannot flush installation file');
        } finally { fclose($file); }
        if (!rename($temp, $path)) { @unlink($temp); throw new RuntimeException('Cannot publish installation file'); }
        @chmod($path, 0600);
    }

    // =========================================================================
    // INPUT GROUPS AND PRIVATE STORAGE PATHS
    // =========================================================================

    private function fields(array $data, array $allowed, string $field): void {
        if (array_diff(array_keys($data), $allowed)) V::fail($field, 'UNKNOWN_FIELD');
    }

    private function group(mixed $value, string $field): array {
        if (!is_array($value) || ($value && array_is_list($value))) V::fail($field);
        return $value;
    }

    /**
     * Resolve an absolute writable destination outside protected application
     * directories.
     */
    private function storage_path(mixed $value): string {
        $path = str_replace('\\', '/', V::text($value, 'site.storagePath', 1, 2048));
        if (
            !preg_match('~^(?:[A-Za-z]:/|/)~', $path) || str_starts_with($path, '//')
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)
        ) V::fail('site.storagePath');
        $parent = rtrim($path, '/'); $missing = [];
        while (!file_exists($parent)) {
            $missing[] = basename($parent); $next = dirname($parent);
            if ($next === $parent) V::fail('site.storagePath');
            $parent = $next;
        }
        if (!is_dir($parent) || !is_writable($parent)) V::fail('site.storagePath', 'NOT_WRITABLE');
        $resolved = str_replace('\\', '/', realpath($parent));
        if ($missing) $resolved .= '/' . implode('/', array_reverse($missing));
        $candidate = strtolower(rtrim($resolved, '/'));
        foreach (['public', 'config', 'src', 'database', 'bin', 'tests', 'docs', 'contracts'] as $directory) {
            $protected = strtolower(str_replace(
                '\\', '/', realpath($this->root . '/' . $directory) ?: $this->root . '/' . $directory
            ));
            if (
                $candidate === $protected || str_starts_with($candidate, $protected . '/')
                || str_starts_with($protected, $candidate . '/')
            ) V::fail('site.storagePath', 'PRIVATE_DIRECTORY_REQUIRED');
        }
        return $resolved;
    }

    private function bytes(string $value): int {
        if ($value === '-1') return PHP_INT_MAX;
        $unit = strtolower(substr(trim($value), -1));
        return (int)$value * match($unit) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
    }

    // =========================================================================
    // RUNTIME REQUIREMENTS
    // =========================================================================

    public function requirements(): array {
        $checks = [['id' => 'php', 'required' => true, 'passed' => PHP_VERSION_ID >= 80200, 'value' => PHP_VERSION]];
        $drivers = [];
        foreach (['pgsql' => '15', 'mysql' => '8.4', 'sqlite' => '3.38'] as $driver => $version) $drivers[] = [
            'id' => $driver, 'available' => extension_loaded('pdo_' . $driver), 'minimumVersion' => $version,
        ];
        $checks[] = [
            'id' => 'pdo_database', 'required' => true,
            'passed' => (bool)array_filter($drivers, fn($driver) => $driver['available']),
            'value' => implode(', ', array_column(array_filter($drivers, fn($driver) => $driver['available']), 'id')),
        ];
        foreach (['intl', 'zip', 'mbstring', 'fileinfo', 'openssl'] as $extension) $checks[] = [
            'id' => $extension, 'required' => true, 'passed' => extension_loaded($extension),
            'value' => extension_loaded($extension) ? 'loaded' : 'missing',
        ];
        $checks[] = ['id' => 'config_writable', 'required' => true, 'passed' => is_writable($this->root . '/config')];
        $limits = [
            'memory_limit' => 128 * 1024 * 1024,
            'upload_max_filesize' => 50 * 1024 * 1024, 'post_max_size' => 60 * 1024 * 1024,
        ];
        foreach ($limits as $name => $minimum) $checks[] = [
            'id' => $name, 'required' => false,
            'passed' => $this->bytes(ini_get($name)) >= $minimum, 'value' => ini_get($name),
        ];
        $checks[] = [
            'id' => 'storage_writable', 'required' => true,
            'passed' => is_dir($this->root . '/storage') ? is_writable($this->root . '/storage')
                : is_writable($this->root),
        ];
        return [
            'ready' => !array_filter($checks, fn($check) => $check['required'] && !$check['passed']),
            'checks' => $checks, 'database' => ['drivers' => $drivers],
            'limits' => ['addonZipMaxBytes' => 50 * 1024 * 1024, 'imageMaxBytes' => 2 * 1024 * 1024],
        ];
    }

    // =========================================================================
    // DATABASE INPUT AND CONNECTION PROBES
    // =========================================================================

    private function database_values(array $data): array {
        $driver = V::choice($data['driver'] ?? 'pgsql', 'database.driver', ['pgsql', 'mysql', 'sqlite']);
        if (!extension_loaded('pdo_' . $driver)) throw new ApiException(422, 'DATABASE_DRIVER_MISSING');
        $create = V::boolean($data['createIfMissing'] ?? false, 'database.createIfMissing');
        if ($driver === 'sqlite') {
            $this->fields($data, ['driver', 'path', 'createIfMissing'], 'database');
            $path = str_replace('\\', '/', V::text($data['path'] ?? null, 'database.path', 1, 2048));
            if (
                !preg_match('~^(?:[A-Za-z]:/|/)~', $path) || str_starts_with($path, '//')
                || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path) || str_contains($path, "\0")
                || !preg_match('/\.(sqlite|sqlite3|db)$/i', $path) || is_link($path)
            ) V::fail('database.path');
            // SQLite needs an existing writable parent and a private database
            // file location.
            $directory = realpath(dirname($path));
            if (
                !$directory || !is_dir($directory) || !is_writable($directory)
            ) V::fail('database.path', 'DIRECTORY_REQUIRED');
            $directory = str_replace('\\', '/', $directory); $path = $directory . '/' . basename($path);
            foreach (['public', 'config', 'src', 'database', 'bin', 'tests', 'docs', 'contracts'] as $protected) {
                $base = strtolower(str_replace(
                    '\\', '/', realpath($this->root . '/' . $protected) ?: $this->root . '/' . $protected
                ));
                if (
                    str_starts_with(strtolower($path), $base . '/')
                ) V::fail('database.path', 'PRIVATE_DIRECTORY_REQUIRED');
            }
            $documentRoot = realpath(($_SERVER['DOCUMENT_ROOT'] ?? '') ?: $this->root . '/public');
            if (
                $documentRoot && str_starts_with(
                    strtolower($path), strtolower(str_replace('\\', '/', $documentRoot)) . '/'
                )
            ) V::fail('database.path', 'PRIVATE_DIRECTORY_REQUIRED');
            if (
                file_exists($path) && (!is_file($path) || !is_writable($path))
            ) V::fail('database.path', 'NOT_WRITABLE');
            return ['dsn' => 'sqlite:' . $path, 'db_user' => '', 'db_password' => '', 'create_database' => $create];
        }
        $this->fields($data, [
            'driver', 'host', 'port', 'name', 'username', 'password', 'sslMode', 'sslCa', 'createIfMissing',
        ], 'database');
        $host = V::text($data['host'] ?? '127.0.0.1', 'database.host', 1, 253);
        if (!preg_match('/^[A-Za-z0-9._:-]+$/D', $host)) V::fail('database.host');
        $port = V::integer($data['port'] ?? ($driver === 'mysql' ? 3306 : 5432), 'database.port', 1, 65535);
        $name = V::text($data['name'] ?? null, 'database.name', 1, 63);
        if (
            !preg_match('/^[a-z_][a-z0-9_]*$/D', $name) || in_array($name, [
                'postgres', 'template0', 'template1', 'mysql', 'sys', 'performance_schema', 'information_schema',
            ], true)
        ) V::fail('database.name');
        $username = V::text($data['username'] ?? null, 'database.username', 1, 63);
        $password = V::text($data['password'] ?? '', 'database.password', 0, 1024);
        if ($driver === 'mysql') {
            $ca = V::text($data['sslCa'] ?? '', 'database.sslCa', 0, 2048);
            if ($ca !== '' && (!is_file($ca) || !is_readable($ca))) V::fail('database.sslCa');
            return [
                'dsn' => "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", 'db_user' => $username,
                'db_password' => $password, 'db_ssl_ca' => $ca, 'create_database' => $create,
            ];
        }
        $ssl = V::choice($data['sslMode'] ?? 'prefer', 'database.sslMode', [
            'disable', 'prefer', 'require', 'verify-ca', 'verify-full',
        ]);
        $create = V::boolean($data['createIfMissing'] ?? false, 'database.createIfMissing');
        return [
            'dsn' => "pgsql:host=$host;port=$port;dbname=$name;sslmode=$ssl;connect_timeout=5",
            'db_user' => $username, 'db_password' => $password, 'create_database' => $create,
        ];
    }

    private function connect(array $config): PDO {
        $db = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec("SET statement_timeout='5s'; SET default_transaction_read_only=on");
        return $db;
    }

    private function schema_empty(PDO $db): bool { return (new Schema($this->root))->is_empty($db); }

    /**
     * Check PostgreSQL version, encoding, schema eligibility, and creation
     * permissions.
     */
    private function probe(array $config): array {
        if (!str_starts_with($config['dsn'], 'pgsql:')) return $this->probe_portable($config);
        preg_match('/;dbname=([^;]+)/', $config['dsn'], $match); $name = $match[1]; $exists = true;
        try { $db = $this->connect($config); }
        catch (PDOException) {
            try {
                $maintenance = $config;
                $maintenance['dsn'] = str_replace(';dbname=' . $name . ';', ';dbname=postgres;', $config['dsn']);
                $db = $this->connect($maintenance);
                $query = $db->prepare('SELECT EXISTS(SELECT 1 FROM pg_database WHERE datname=:name)');
                $query->execute(['name' => $name]);
                if ($query->fetchColumn()) throw new ApiException(422, 'DATABASE_CONNECTION_FAILED');
                $exists = false;
            } catch (PDOException) { throw new ApiException(422, 'DATABASE_CONNECTION_FAILED'); }
        }
        $version = (int)$db->query("SELECT current_setting('server_version_num')::int")->fetchColumn();
        $sql = 'SELECT rolcreatedb OR rolsuper FROM pg_roles WHERE rolname=current_user';
        $can_create = (bool)$db->query($sql)->fetchColumn(); $empty = !$exists || $this->schema_empty($db);
        $sql = "SELECT has_database_privilege(current_user,current_database(),'CREATE')";
        $permissions = !$exists || (bool)$db->query($sql)->fetchColumn();
        $encoding = !$exists || $db->query("SHOW server_encoding")->fetchColumn() === 'UTF8';
        $reason = match(true) {
            $version < 150000 => 'DATABASE_VERSION_UNSUPPORTED', !$encoding => 'DATABASE_ENCODING_UNSUPPORTED',
            !$empty => 'DATABASE_NOT_EMPTY', !$permissions => 'DATABASE_PERMISSION_REQUIRED',
            !$exists && !($config['create_database'] && $can_create) => 'DATABASE_CREATION_REQUIRED', default => null,
        };
        return [
            'connected' => true, 'databaseExists' => $exists, 'canCreateDatabase' => $can_create,
            'schemaEmpty' => $empty, 'serverVersion' => $db->query('SHOW server_version')->fetchColumn(),
            'ready' => $reason === null, 'reason' => $reason,
        ];
    }

    /**
     * Inspect SQLite or MySQL readiness without creating the requested
     * database.
     */
    private function probe_portable(array $config): array {
        $sqlite = str_starts_with($config['dsn'], 'sqlite:'); $exists = true; $canCreate = false;
        try {
            if ($sqlite) {
                $exists = is_file(substr($config['dsn'], 7));
                $canCreate = is_writable(dirname(substr($config['dsn'], 7)));
                // Never create the requested file during a connection test.
                $pdo = $exists ? (new Db($config))->pdo : new PDO('sqlite::memory:');
                $version = $pdo->query('SELECT sqlite_version()')->fetchColumn();
                $supported = version_compare($version, '3.38', '>=');
            } else {
                try { $pdo = (new Db($config))->pdo; }
                catch (PDOException $error) {
                    if (($error->errorInfo[1] ?? 0) !== 1049) throw $error;
                    $exists = false; $admin = $config;
                    $admin['dsn'] = preg_replace('/;dbname=[^;]+/', '', $config['dsn']); $pdo = (new Db($admin))->pdo;
                }
                $version = $pdo->query('SELECT version()')->fetchColumn();
                $supported = !str_contains(strtolower($version), 'mariadb') && version_compare($version, '8.4', '>=');
                foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
                    if (preg_match('/^GRANT (?:ALL PRIVILEGES|.*\bCREATE\b.*) ON /', $grant)) $canCreate = true;
                }
            }
            $empty = !$exists || $this->schema_empty($pdo);
            $reason = match(true) {
                !$supported => 'DATABASE_VERSION_UNSUPPORTED', !$empty => 'DATABASE_NOT_EMPTY',
                !$exists && !($config['create_database'] && $canCreate) => 'DATABASE_CREATION_REQUIRED',
                default => null,
            };
            return [
                'connected' => true, 'databaseExists' => $exists, 'canCreateDatabase' => $canCreate,
                'schemaEmpty' => $empty, 'serverVersion' => $version, 'ready' => $reason === null, 'reason' => $reason,
            ];
        } catch (PDOException) { throw new ApiException(422, 'DATABASE_CONNECTION_FAILED'); }
    }

    public function database(): array {
        $this->fields($this->request->body, ['database'], 'request');
        return $this->probe($this->database_values($this->group($this->request->input('database'), 'database')));
    }

    // =========================================================================
    // INSTALLATION PLAN AND CONFIGURATION PUBLICATION
    // =========================================================================

    private function prepare(): array {
        $body = $this->request->body; $this->fields($body, ['database', 'site', 'admin', 'mail'], 'request');
        $database = $this->database_values($this->group($body['database'] ?? null, 'database'));
        $site = $this->group($body['site'] ?? null, 'site');
        $this->fields($site, ['url', 'storagePath', 'name', 'language', 'timezone'], 'site');
        $url = rtrim(V::text($site['url'] ?? null, 'site.url', 1, 2048), '/'); $parts = parse_url($url);
        if (
            !filter_var($url, FILTER_VALIDATE_URL) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !empty($parts['path'])
        ) V::fail('site.url');
        $storage = $this->storage_path($site['storagePath'] ?? $this->root . '/storage');
        $metadata = [
            'name' => V::text($site['name'] ?? 'RoG Addons', 'site.name', 1, 120),
            'language' => V::choice($site['language'] ?? 'en', 'site.language', [
                'en', 'es', 'pt', 'de', 'fr', 'it', 'ru', 'pl', 'ko', 'zh',
            ]),
            'timezone' => V::choice($site['timezone'] ?? 'UTC', 'site.timezone', DateTimeZone::listIdentifiers()),
        ];
        $admin = $this->group($body['admin'] ?? null, 'admin');
        $this->fields($admin, ['username', 'email', 'password', 'passwordConfirmation'], 'admin');
        $username = V::text($admin['username'] ?? null, 'admin.username', 3, 24);
        $email = V::email($admin['email'] ?? null);
        $password = V::text($admin['password'] ?? null, 'admin.password', 12, 128);
        if (
            ($admin['passwordConfirmation'] ?? null) !== $password
        ) V::fail('admin.passwordConfirmation', 'PASSWORD_MISMATCH');
        $mail = $this->group($body['mail'] ?? [], 'mail');
        $this->fields($mail, ['transport', 'from', 'host', 'port', 'username', 'password'], 'mail');
        $transport = V::choice($mail['transport'] ?? 'spool', 'mail.transport', ['spool', 'smtp']);
        $host = V::text($mail['host'] ?? '', 'mail.host', 0, 253);
        if ($transport === 'smtp' && !preg_match('/^[A-Za-z0-9.-]+$/D', $host)) V::fail('mail.host');
        $candidate = $database + [
            'secret' => bin2hex(random_bytes(32)), 'origin' => $url, 'storage' => $storage,
            'secure_cookie' => $parts['scheme'] === 'https', 'mail_transport' => $transport,
            'mail_from' => V::email($mail['from'] ?? 'noreply@example.invalid'), 'smtp_host' => $host,
            'smtp_port' => V::integer($mail['port'] ?? 587, 'mail.port', 1, 65535),
            'smtp_user' => V::text($mail['username'] ?? '', 'mail.username', 0, 254),
            'smtp_password' => V::text($mail['password'] ?? '', 'mail.password', 0, 1024),
        ];
        $probe = $this->probe($candidate);
        if (!$probe['ready']) throw new ApiException(409, $probe['reason']);
        return [
            'id' => bin2hex(random_bytes(24)), 'config' => $candidate, 'site' => $metadata,
            // The journal keeps the founder password hash rather than the
            // submitted password.
            'admin' => ['username' => $username, 'email' => $email, 'password_hash' => Passwords::hash($password)],
            'createdAt' => gmdate('c'),
        ];
    }

    protected function stage_config(array $journal): void {
        $contents = "<?php\n// Generated by the installation service. Keep private.\nreturn ";
        $contents .= var_export($journal['config'], true) . ";\n";
        $this->write_private($this->path('setup.pending.php'), $contents);
    }

    /**
     * Mark ownership before publishing the local configuration and retire
     * temporary setup credentials.
     */
    protected function publish_config(array $journal): void {
        if (is_file($this->path('config.local.php'))) throw new ApiException(409, 'SETUP_LOCKED');
        $this->write_private($this->path('install.lock'), $journal['id'] . "\n");
        $error = 'Cannot publish configuration';
        if (
            !rename($this->path('setup.pending.php'), $this->path('config.local.php'))
        ) throw new RuntimeException($error);
        if (function_exists('opcache_invalidate')) opcache_invalidate($this->path('config.local.php'), true);
        @unlink($this->path('setup.token.json')); @unlink($this->path('setup.pending.json'));
    }

    // =========================================================================
    // RESUMABLE INITIAL INSTALLATION
    // =========================================================================

    public function install(): array {
        return $this->guard(function () {
            if ($this->locked()) throw new ApiException(409, 'SETUP_LOCKED');
            if (!$this->requirements()['ready']) throw new ApiException(409, 'REQUIREMENTS_FAILED');
            // Reuse the saved plan so retries keep the same installation
            // identity and secrets.
            $journal = $this->pending(); $resumed = $journal !== null;
            if ($journal === null) {
                $journal = $this->prepare();
                $this->write_private($this->path('setup.pending.json'), $this->encode($journal));
            }
            $committed = false;
            try {
                $storage = $this->storage_path($journal['config']['storage']); $error = 'Cannot create storage';
                if (
                    !is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)
                ) throw new RuntimeException($error);
                $db = new Db($journal['config'], createDatabase: true);
                $initialize = function () use ($db, $journal) {
                    if (
                        $db->driver === 'mysql'
                        && !($db->has_table('settings') && (new Setting($db))->get('installation'))
                    ) (new Schema($this->root))->create($db, $journal['id']);
                    return $db->transaction(function () use ($db, $journal) {
                        $db->lock('rog:install'); $previous = null;
                        if ($db->has_table('settings')) $previous = (new Setting($db))->get('installation');
                        // A committed installation with the same identity only
                        // needs configuration staging/finalization.
                        if ($previous !== null && ($previous->value->id ?? '') === $journal['id']) {
                            $this->stage_config($journal);
                            return ['founderId' => $previous->value->founderId];
                        }
                        if ($previous !== null) throw new ApiException(409, 'DATABASE_NOT_EMPTY');
                        if ($db->driver !== 'mysql') {
                            if (!$this->schema_empty($db->pdo)) throw new ApiException(409, 'DATABASE_NOT_EMPTY');
                            (new Schema($this->root))->create($db);
                        }
                        $founder = (new Account($db))->create($journal['admin'] + [
                            'verified_at' => gmdate('c'), 'access_level' => 2,
                        ]);
                        $settings = new Setting($db);
                        $settings->create(['key' => 'site', 'value' => (object)$journal['site']]);
                        $settings->create(['key' => 'installation', 'value' => (object)[
                            'id' => $journal['id'], 'founderId' => $founder->id, 'installedAt' => gmdate('c'),
                        ]]);
                        if ($db->driver === 'mysql') $db->query("DELETE FROM settings WHERE key='initial_schema'");
                        $this->stage_config($journal);
                        return ['founderId' => $founder->id];
                    });
                };
                $result = $db->driver === 'mysql' ? $db->exclusive('rog:install', $initialize) : $initialize();
                // Publish the configuration after database initialization has
                // completed successfully.
                $committed = true; $this->publish_config($journal);
            } catch (ApiException $error) { throw $error; }
            catch (Throwable $error) {
                error_log('Installation ' . $journal['id'] . ': ' . get_class($error));
                throw new ApiException(
                    500, $committed ? 'INSTALL_FINALIZE_FAILED' : 'INSTALL_FAILED', details: ['resumable' => true]
                );
            }
            return [
                'installed' => true, 'locked' => true, 'resumed' => $resumed, 'founderId' => $result['founderId'],
                'siteUrl' => $journal['config']['origin'], 'mailTransport' => $journal['config']['mail_transport'],
                'steps' => [
                    'database', 'model_schema', 'founder', 'site_settings', 'private_configuration', 'installer_lock',
                ],
            ];
        });
    }
}
