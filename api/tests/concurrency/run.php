<?php

/**
 * Addons ROG - Concurrent database behavior tests
 *
 * - Release synchronized workers against shared model, vote, download, and review fixtures.
 * - Require stale-write protection and deduplication under concurrent requests.
 */

declare(strict_types=1);

// =============================================================================
// DISPOSABLE ENVIRONMENT AND SHARED FIXTURES
// =============================================================================

require dirname(__DIR__) . '/support/results.php';
$config = require dirname(__DIR__, 2) . '/bootstrap.php';
if (
    getenv('ROG_TEST_MODE') !== 'disposable' || $config['db_user'] !== 'rog_test'
) throw new RuntimeException('Disposable test database required');
$db = new Rog\Database\Db($config); $settings = new Rog\Models\Setting($db);
$settings->create(['key' => 'concurrent', 'value' => (object) ['counter' => 0]]);
$owner = $db->value("SELECT id FROM accounts WHERE username='AuthorOne'");
$request = $db->value(
    "INSERT INTO requests(author_id,addon_name,expansion_id,details) VALUES "
        . "(:id,'Concurrent votes','wotlk','Test concurrent desired votes') RETURNING id",
    ['id' => $owner]
);
$version = $db->value('SELECT id FROM published_versions ORDER BY id DESC LIMIT 1');
$addon = $db->value('SELECT addon_id FROM published_versions WHERE id=:id', ['id' => $version]);
$results = [];

// =============================================================================
// SYNCHRONIZED MUTATION SCENARIOS
// =============================================================================

foreach (
    [
        'model' => '0', 'vote' => (string)$request, 'download' => (string)$version, 'review' => (string)$addon
    ] as $mode => $id
) {
    $barrier = $config['storage'] . '/barrier-' . $mode; $processes = [];
    // Two model writers compete on one revision; the other scenarios race
    // twelve workers.
    $count = $mode === 'model' ? 2 : 12;
    for ($i = 0; $i < $count; $i++) {
        $command = [PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get('extension_dir')];
        foreach (['pdo_' . $db->driver, 'intl', 'mbstring', 'openssl', 'zip', 'fileinfo'] as $extension) {
            $command[] = '-d'; $command[] = 'extension=' . $extension;
        }
        $command = array_merge(
            $command, [dirname(__DIR__) . '/support/workers/concurrency.php', $mode, $id, $barrier, (string)$i]
        );
        $pipes = [];
        $process = proc_open(
            $command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null,
            ['bypass_shell' => true, 'create_no_window' => true]
        );
        if (!is_resource($process)) throw new RuntimeException('Cannot create test worker');
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    $deadline = microtime(true) + 30;
    while (count(glob($barrier . '.*')) < $count) {
        if (microtime(true) > $deadline) throw new RuntimeException('Worker readiness timeout');
        usleep(10000);
    }
    // Release the batch only after every worker has signaled readiness.
    file_put_contents($barrier, 'go');
    $outputs = [];
    foreach ($processes as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Worker failed: ' . $error . $output);
        $outputs[] = $output;
    }
    if (
        $mode === 'model' && (
            count(array_filter($outputs, fn($x) => $x === 'saved')) !== 1 || count(
                array_filter($outputs, fn($x) => $x === 'conflict')
            ) !== 1
        )
    ) throw new RuntimeException('Lost-update protection failed');
    if (
        $mode === 'vote' && (int)$db->value(
            "SELECT count(*) FROM interactions WHERE request_id=:id AND kind='request_vote'", ['id' => $request]
        ) !== 1
    ) throw new RuntimeException('Concurrent vote deduplication failed');
    if (
        $mode === 'download' && (int)$db->value(
            "SELECT count(*) FROM counted_downloads WHERE submission_id=:id AND "
                . "client_key=decode(repeat('ab',32),'hex')",
            ['id' => $version]
        ) !== 1
    ) throw new RuntimeException('Concurrent download deduplication failed');
    if (
        $mode === 'review' && (int)$db->value(
            'SELECT count(*) FROM comments WHERE addon_id=:id AND author_id=(SELECT id FROM '
                . 'accounts WHERE username=:name)',
            ['id' => $addon, 'name' => 'Founder']
        ) !== 1
    ) throw new RuntimeException('Concurrent review deduplication failed');
    // Record outcomes only after checking the persisted result for the
    // completed batch.
    $results[$mode] = ['workers' => $count, 'results' => array_count_values($outputs), 'passed' => true];
    foreach (glob($barrier . '.*') as $file) unlink($file);
    unlink($barrier);
}
file_put_contents(
    test_result_path('concurrency-results-' . $db->driver . '.json'),
    json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
);
echo "4 concurrent scenarios passed\n";
