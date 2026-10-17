<?php

/**
 * Addons ROG - Disposable API test orchestration
 *
 * - Launch isolated database and HTTP processes with temporary credentials and storage.
 * - Run schema, installer, integration, and concurrency suites, then stop managed processes.
 */

declare(strict_types=1);

// =============================================================================
// MANAGED CHILD PROCESSES
// =============================================================================

final class TestProcess {
    private mixed $handle;
    private ?int $exit = null;

    public function __construct(
        array $command, string $cwd, array $env, public readonly string $log
    ) {
        $pipes = []; $input = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $this->handle = proc_open($command, [
            0 => ['file', $input, 'r'], 1 => ['file', $log, 'a'], 2 => ['redirect', 1],
        ], $pipes, $cwd, $env, ['bypass_shell' => true, 'create_no_window' => true]);
        if (!is_resource($this->handle)) throw new RuntimeException('Cannot start test process');
    }

    public function running(): bool {
        if (!is_resource($this->handle)) return false;
        $status = proc_get_status($this->handle);
        if (!$status['running'] && $this->exit === null) $this->exit = $status['exitcode'];
        return $status['running'];
    }

    /**
     * Wait for a bounded interval and surface the captured log when a child
     * fails.
     */
    public function finish(int $timeout = 180): string {
        $deadline = microtime(true) + $timeout;
        while ($this->running()) {
            if (microtime(true) >= $deadline) {
                $this->stop(); throw new RuntimeException('Test process timed out; see ' . $this->log);
            }
            usleep(50000);
        }
        $code = proc_close($this->handle); $this->handle = null;
        $code = $this->exit ?? $code; $output = file_get_contents($this->log);
        if ($code !== 0) throw new RuntimeException('Test process failed (' . $code . "):\n" . $output);
        return $output;
    }

    public function stop(): void {
        if (!is_resource($this->handle)) return;
        if ($this->running()) proc_terminate($this->handle);
        $deadline = microtime(true) + 5;
        while ($this->running() && microtime(true) < $deadline) usleep(50000);
        if ($this->running()) proc_terminate($this->handle, 9);
        proc_close($this->handle); $this->handle = null;
    }
}

// =============================================================================
// DISPOSABLE TEST ENVIRONMENT
// =============================================================================

final class TestRunner {
    private string $root;
    private string $work;
    private string $php;
    private array $flags = [];
    private array $env;
    private int $httpPort;
    private int $logIndex = 0;
    private array $processes = [];

    /**
     * Refuse local deployment overrides and generate a separate temporary
     * environment for this run.
     */
    public function __construct(private string $driver) {
        $this->root = dirname(__DIR__, 2);
        if (is_file($this->root . '/config/config.local.php'))
            throw new RuntimeException('Tests refuse config.local.php overrides. Run from a clean copy.');
        if (!in_array($driver, ['sqlite', 'mysql', 'pgsql'], true))
            throw new InvalidArgumentException('Use sqlite|mysql|pgsql');
        $base = getenv('ROG_TEST_WORKDIR') ?: sys_get_temp_dir();
        $this->work = $base . '/rog-tests-' . bin2hex(random_bytes(8));
        if (!mkdir($this->work, 0700, true)) throw new RuntimeException('Cannot create disposable test directory');
        $this->work = realpath($this->work); $this->php = getenv('PHP_BIN') ?: PHP_BINARY;
        if ($extensions = getenv('PHP_EXTENSION_DIR')) {
            $this->flags = ['-n', '-d', 'extension_dir=' . $extensions];
            foreach (['pdo_' . $driver, 'intl', 'zip', 'mbstring', 'openssl', 'fileinfo'] as $extension) {
                $this->flags[] = '-d'; $this->flags[] = 'extension=' . $extension;
            }
        }
        foreach (['upload_max_filesize=50M', 'post_max_size=60M', 'memory_limit=512M', 'display_errors=0'] as $flag) {
            $this->flags[] = '-d'; $this->flags[] = $flag;
        }
        $this->httpPort = $this->port();
        $this->env = array_replace(getenv(), [
            'ROG_DSN' => 'sqlite:' . $this->work . '/database.sqlite', 'ROG_DB_USER' => 'rog_test',
            'ROG_DB_PASSWORD' => '', 'ROG_SECRET' => bin2hex(random_bytes(32)),
            'ROG_STORAGE' => $this->work . '/storage', 'ROG_TEST_RESULTS' => $this->work . '/results',
            'ROG_ORIGIN' => 'http://127.0.0.1:' . $this->httpPort, 'ROG_SECURE_COOKIE' => '0',
            'ROG_CREATE_DATABASE' => '1', 'ROG_TEST_MODE' => 'disposable', 'ROG_MAIL_TRANSPORT' => 'spool',
        ]);
    }

    // =========================================================================
    // PROCESS, PORT, AND HTTP HELPERS
    // =========================================================================

    private function port(): int {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        if ($socket === false) throw new RuntimeException('Cannot reserve test port');
        $address = stream_socket_get_name($socket, false); fclose($socket);
        return (int)substr(strrchr($address, ':'), 1);
    }

    private function binary(string $variable, string $name): string {
        $directory = getenv($variable);
        if (!$directory) throw new RuntimeException('Set ' . $variable . ' to the database binaries directory');
        return $directory . '/' . $name . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    }

    private function start(
        array $command, ?array $env = null, ?string $cwd = null, ?string $log = null
    ): TestProcess {
        $log ??= 'command-' . ++$this->logIndex . '.log';
        $process = new TestProcess($command, $cwd ?? $this->root, $env ?? $this->env, $this->work . '/' . $log);
        $this->processes[] = $process;
        return $process;
    }

    private function command(array $command, ?array $env = null, ?string $cwd = null): string {
        return $this->start($command, $env, $cwd)->finish();
    }

    private function php(array $args, ?array $env = null, ?string $cwd = null): string {
        return $this->command([$this->php, ...$this->flags, ...$args], $env, $cwd);
    }

    private function ready(TestProcess $server, string $origin): void {
        $deadline = microtime(true) + 15;
        do {
            $context = stream_context_create(['http' => ['timeout' => 0.5, 'ignore_errors' => true]]);
            if (@file_get_contents($origin . '/api/health', false, $context) !== false) return;
            if (!$server->running()) throw new RuntimeException('HTTP server exited; see ' . $server->log);
            usleep(100000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('HTTP readiness timeout; see ' . $server->log);
    }

    private function server(string $root, array $env, int $port, string $log): TestProcess {
        $server = $this->start([
            $this->php, ...$this->flags, '-S', '127.0.0.1:' . $port, '-t', 'public', 'public/router.php',
        ], $env, $root, $log);
        $this->ready($server, 'http://127.0.0.1:' . $port);
        return $server;
    }

    /**
     * Copy installer sources into the disposable workspace without following
     * symbolic links.
     */
    private function copy(string $source, string $target): void {
        if (is_link($source)) throw new RuntimeException('Test copy refuses symbolic links');
        if (!is_dir($source)) {
            if (!copy($source, $target)) throw new RuntimeException('Cannot copy test fixture');
            return;
        }
        if (!mkdir($target, 0700, true)) throw new RuntimeException('Cannot create test copy');
        foreach (new DirectoryIterator($source) as $file) {
            if (!$file->isDot()) $this->copy($file->getPathname(), $target . '/' . $file->getFilename());
        }
    }

    // =========================================================================
    // ISOLATED WEB INSTALLER SUITE
    // =========================================================================

    private function installer(): void {
        $copy = $this->work . '/installer-copy-' . bin2hex(random_bytes(6)); $this->copy($this->root, $copy);
        $port = $this->port(); $origin = 'http://127.0.0.1:' . $port;
        $env = array_replace($this->env, [
            'ROG_DSN' => '', 'ROG_DB_USER' => '', 'ROG_DB_PASSWORD' => '', 'ROG_SECRET' => '',
            'ROG_SETUP_TOKEN' => '', 'ROG_ORIGIN' => $origin,
        ]);
        // Issue setup credentials inside the copied API so the working
        // deployment is not configured.
        $issued = json_decode($this->php(['bin/setup-token.php'], $env, $copy), true, 512, JSON_THROW_ON_ERROR);
        $server = $this->server($copy, $env, $port, 'installer-http.log');
        try {
            $args = ['-d', 'display_errors=1', 'tests/installation/web-installer.php'];
            echo $this->php($args, array_replace($this->env, [
                'ROG_SETUP_TEST_URL' => $origin, 'ROG_SETUP_TEST_TOKEN' => $issued['token'],
            ]), $copy);
        } finally { $server->stop(); }
    }

    // =========================================================================
    // PARALLEL POSTGRESQL CONNECTION CHECKS
    // =========================================================================

    private function bootstrap(): void {
        echo $this->php(['-d', 'display_errors=1', 'tests/database/postgres-schema.php', 'prepare']);
        // Release all connection workers together to check that ordinary
        // connections never create tables.
        $barrier = $this->work . '/bootstrap'; $workers = [];
        try {
            for ($i = 0; $i < 4; $i++) $workers[] = $this->start([
                $this->php, ...$this->flags, 'tests/support/workers/bootstrap.php', $barrier, (string)$i,
            ]);
            $deadline = microtime(true) + 30;
            while (count(glob($barrier . '.*')) < 4) {
                if (microtime(true) >= $deadline) throw new RuntimeException('Bootstrap readiness timeout');
                usleep(100000);
            }
        } finally { file_put_contents($barrier, 'go'); }
        $errors = [];
        foreach ($workers as $worker) {
            try { echo $worker->finish(); } catch (Throwable $error) { $errors[] = $error; }
        }
        foreach (glob($barrier . '*') as $file) unlink($file);
        if ($errors) throw $errors[0];
        echo $this->php(['-d', 'display_errors=1', 'tests/database/postgres-schema.php']);
    }

    // =========================================================================
    // DISPOSABLE DATABASE SERVERS
    // =========================================================================

    private function postgres(int $port, string $password): void {
        $this->env['ROG_DB_PASSWORD'] = $password;
        $this->env['ROG_DSN'] = "pgsql:host=127.0.0.1;port=$port;dbname=rog_test_app";
        $file = $this->work . '/password.txt'; $old = umask(0077);
        try { file_put_contents($file, $password); } finally { umask($old); }
        $this->command([
            $this->binary('PG_BIN', 'initdb'), '-D', $this->work . '/cluster', '-U', 'rog_test', '--encoding=UTF8',
            '--locale=C', '--auth-host=scram-sha-256', '--auth-local=trust', '--pwfile=' . $file,
        ]);
        $this->command([
            $this->binary('PG_BIN', 'pg_ctl'), '-D', $this->work . '/cluster', '-l', $this->work . '/postgres.log',
            '-w', '-t', '30', '-o', "-h 127.0.0.1 -p $port -c max_connections=30", 'start',
        ]);
    }

    /**
     * Initialize a private server with temporary credentials and wait for it to
     * accept connections.
     */
    private function mysql(int $port, string $password): TestProcess {
        $this->env['ROG_DB_PASSWORD'] = $password;
        $this->env['ROG_DSN'] = "mysql:host=127.0.0.1;port=$port;dbname=rog_test_app;charset=utf8mb4";
        $data = $this->work . '/mysql-data'; $init = $this->work . '/initialize.sql';
        $this->command([
            $this->binary('MYSQL_BIN', 'mysqld'), '--no-defaults', '--initialize-insecure', '--datadir=' . $data,
        ]);
        $sql = "ALTER USER 'root'@'localhost' IDENTIFIED BY '$password';\n";
        $sql .= "CREATE USER 'rog_test'@'127.0.0.1' IDENTIFIED BY '$password';\n";
        $sql .= "GRANT ALL PRIVILEGES ON *.* TO 'rog_test'@'127.0.0.1';\n";
        $old = umask(0077);
        try { file_put_contents($init, $sql); } finally { umask($old); }
        $server = $this->start([
            $this->binary('MYSQL_BIN', 'mysqld'), '--no-defaults', '--datadir=' . $data, '--bind-address=127.0.0.1',
            '--port=' . $port, '--mysqlx=0', '--init-file=' . $init, '--console',
        ], cwd: $this->work, log: 'mysql.log');
        $deadline = microtime(true) + 30;
        do {
            try { $this->mysql_admin($port, 'ping'); unlink($init); return $server; } catch (RuntimeException $error) {
                if (!$server->running() || microtime(true) >= $deadline) throw $error;
                usleep(200000);
            }
        } while (true);
    }

    private function mysql_admin(int $port, string $action): void {
        $this->command([
            $this->binary('MYSQL_BIN', 'mysqladmin'), '--no-defaults', '--host=127.0.0.1', '--port=' . $port,
            '--user=rog_test', $action,
        ], array_replace($this->env, ['MYSQL_PWD' => $this->env['ROG_DB_PASSWORD']]), $this->work);
    }

    // =========================================================================
    // SUITE EXECUTION AND PROCESS CLEANUP
    // =========================================================================

    public function run(): void {
        $databasePort = $this->port(); $mysql = null; $error = null;
        try {
            $password = bin2hex(random_bytes(24));
            if ($this->driver === 'pgsql') $this->postgres($databasePort, $password);
            if ($this->driver === 'mysql') $mysql = $this->mysql($databasePort, $password);
            if ($this->driver === 'pgsql') $this->bootstrap();
            else {
                echo $this->php(['-d', 'display_errors=1', 'bin/schema.php', 'create']);
                echo $this->php(['-d', 'display_errors=1', 'tests/database/portable-schema.php']);
            }
            $this->installer(); $this->server($this->root, $this->env, $this->httpPort, 'http.log');
            // Run maintenance commands only against this disposable environment
            // after the test scenarios.
            $suites = ['tests/http/run.php', 'tests/concurrency/run.php', 'bin/cleanup.php', 'bin/worker.php'];
            foreach ($suites as $file) {
                echo $this->php(['-d', 'display_errors=1', $file]);
            }
        } catch (Throwable $failure) { $error = $failure; }
        finally {
            // Stop dependents before database shutdown, including when an
            // earlier suite fails.
            foreach (array_reverse($this->processes) as $process) {
                if ($process !== $mysql) $process->stop();
            }
            try {
                if ($mysql && $mysql->running()) $this->mysql_admin($databasePort, 'shutdown');
                if ($this->driver === 'pgsql' && is_file($this->work . '/cluster/postmaster.pid')) $this->command([
                        $this->binary('PG_BIN', 'pg_ctl'), '-D', $this->work . '/cluster',
                        '-m', 'fast', '-w', '-t', '30', 'stop',
                    ]);
            } catch (Throwable $failure) { $error ??= $failure; }
            finally {
                $mysql?->stop();
                foreach (['password.txt', 'initialize.sql'] as $file) {
                    if (is_file($this->work . '/' . $file)) unlink($this->work . '/' . $file);
                }
                echo 'Test results: ' . $this->env['ROG_TEST_RESULTS'] . "\n";
                echo 'Disposable test directory: ' . $this->work . "\n";
            }
        }
        if ($error) throw $error;
    }
}
