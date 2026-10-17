<?php

/**
 * Addons ROG - Statistics response tests
 *
 * - Verify timezone-aware chart dimensions and persisted player presence.
 */

$now = $founder->call('GET', '/api/admin/stats?range=30d&tz=America/New_York');
check(
    count($now['downloads']['current']) === 30 && count($now['primeTime']) === 7 && count($now['primeTime'][0]) === 24,
    'Statistics dimensions'
);
check(
    (int)$db->value("SELECT count(*) FROM events WHERE kind='presence'") > 0,
    'Presence persisted for historical metrics'
);
