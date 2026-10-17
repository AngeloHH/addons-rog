<?php

/**
 * Addons ROG - Public profile privacy tests
 *
 * - Verify that public profile identity omits private account fields.
 */

$public = $guest->call('GET', '/api/profiles/AuthorOne');
check(
    !isset($public['user']['email'], $public['user']['password_hash'], $public['user']['access_level']),
    'Public identity privacy'
);
