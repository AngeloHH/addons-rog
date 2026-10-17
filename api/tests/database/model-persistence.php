<?php

/**
 * Addons ROG - Typed model persistence tests
 *
 * - Verify nested JSON changes, stale-write rejection, allowed columns, and deletion.
 * - Confirm that model use does not create a migration history.
 */

$settings = new Rog\Models\Setting($db);
$setting = $settings->create(['key' => 'test', 'value' => (object) ['nested' => (object) ['x' => 1]]]);
check($setting->value->nested->x === 1, 'Model JSONB hydration');
// Load the same row twice so the second instance becomes stale after the first
// save.
$first = $settings->get('test'); $second = $settings->get('test');
$first->value->nested->x = 2; $first->save();
$second->value->nested->x = 3;
expected_exception(fn() => $second->save(), Rog\Database\Conflict::class, 'Optimistic concurrency');
check($settings->get('test')->value->nested->x === 2, 'Nested JSONB dirty detection');
expected_exception(
    fn() => $settings->filter(['bad;DROP TABLE accounts' => 1]), InvalidArgumentException::class, 'Column allowlist'
);
$first->delete();
check($settings->get('test') === null, 'Model delete');
check(!$db->has_table('schema_migrations'), 'Application uses no migration history');
