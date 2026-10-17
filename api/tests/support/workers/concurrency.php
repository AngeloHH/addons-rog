<?php

/**
 * Addons ROG - Concurrent mutation worker
 *
 * - Race model saves, desired votes, review replacement, or counted downloads.
 * - Report a compact outcome after the parent releases the shared barrier.
 */

declare(strict_types=1);
$config = require dirname(__DIR__, 3) . '/bootstrap.php';
if (
    getenv('ROG_TEST_MODE') !== 'disposable' || $config['db_user'] !== 'rog_test'
) throw new RuntimeException('Disposable test database required');

// =============================================================================
// SHARED FIXTURES AND WORKER BARRIER
// =============================================================================

$db = new Rog\Database\Db($config);
[$script, $mode, $id, $barrier, $worker] = $argv;
// Load the model before the barrier so competing saves start from the same row
// version.
$model = $mode === 'model' ? (new Rog\Models\Setting($db))->get('concurrent'): null;
file_put_contents($barrier . '.' . $worker, 'ready');
$deadline = microtime(true) + 30;
while (!is_file($barrier)) {
    if (microtime(true) > $deadline) throw new RuntimeException('Barrier timeout');
    usleep(10000);
}

// =============================================================================
// CONCURRENT OPERATION
// =============================================================================

if ($mode === 'model') {
    $model->value->counter++;
    try {
        $model->save();
        echo 'saved';
    } catch (Rog\Database\Conflict) {
        echo 'conflict';
    }
} elseif ($mode === 'vote') {
    $app = new Rog\App($config);
    $app->request = new Rog\Http\Request('PUT', '/requests/' . $id . '/vote', body: ['voted' => true]);
    $app->request->params = ['requestId' => $id];
    $app->user = $db->one("SELECT * FROM account_state WHERE username='AuthorOne'");
    (new Rog\Services\RequestService($app))->vote();
    echo 'voted';
} elseif ($mode === 'review') {
    $slug = $db->value('SELECT slug FROM addons WHERE id=:id', ['id' => $id]); $app = new Rog\App($config);
    $app->request = new Rog\Http\Request(
        'PUT', '/addons/' . $slug . '/my-comment',
        body: ['content' => 'Concurrent review', 'rating' => 4, 'expansionId' => 'wotlk']
    );
    $app->request->params = ['slug' => $slug];
    $app->user = $db->one("SELECT * FROM account_state WHERE username='Founder'");
    (new Rog\Services\CommentService($app))->replace();
    echo 'reviewed';
} else {
    $db->record_download((int)$id, null, str_repeat('ab', 32));
    echo 'downloaded';
}
