<?php

/**
 * Addons ROG - Portable model value tests
 *
 * - Round-trip binary token hashes and text-array name claim kinds through model persistence.
 */

// Use arbitrary bytes rather than readable text to expose binary encoding
// mistakes.
$blob = random_bytes(32);
$token = (new Rog\Models\AuthToken($db))->create(
    [
        'purpose' => 'session', 'account_id' => $author_id, 'secret_hash' => $blob,
        'expires_at' => gmdate('c', time() + 60)
    ]
);
check($token->secret_hash === $blob, 'Model bytea round trip');
check($token->to_array()['secret_hash'] === $blob, 'Model bytea array');
$token->delete();
// Verify that array element order survives storage and hydration.
$claim = (new Rog\Models\NameClaim($db))->create(
    ['key' => 'arraytest', 'addon_id' => (int)$aid, 'kinds' => ['alias', 'slug']]
);
check($claim->kinds === ['alias', 'slug'], 'Model native text array');
$claim->delete();
