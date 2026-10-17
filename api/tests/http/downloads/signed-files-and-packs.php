<?php

/**
 * Addons ROG - Signed download and pack tests
 *
 * - Follow signed file redirects, check client deduplication, and download a real pack ZIP.
 */

// =============================================================================
// SIGNED REDIRECT AND COUNTING WINDOW
// =============================================================================

$guest->call('GET', '/api/downloads/' . $sid, null, 302); $location = null;
foreach ($guest->last['headers'] as $h) if (str_starts_with($h, 'Location: ')) $location = substr($h, 10);
check($location !== null, 'Signed redirect');
$guest->call('GET', str_replace($config['origin'], '', $location));
$other->call('GET', '/api/downloads/' . $sid, null, 302);
$other->call('GET', str_replace($config['origin'], '', $location));
check(
    (int)$db->value('SELECT count(*) FROM counted_downloads WHERE submission_id=:id', ['id' => $sid]) === 1,
    'Rolling download deduplication'
);

// =============================================================================
// CONFIGURED PACK ARCHIVE
// =============================================================================

$settings->create(
    [
        'key' => 'packDefinitions',
        'value' => (object) ['tank' => ['name' => 'Tank kit', 'membersByExpansion' => ['wotlk' => [$slug]]]]
    ]
);
$guest->call('GET', '/api/packs/tank/download?expansion=wotlk');
check(str_starts_with($guest->last['raw'], 'PK'), 'Pack binary');
