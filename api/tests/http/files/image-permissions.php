<?php

/**
 * Addons ROG - Uploaded image access tests
 *
 * - Restrict an unpublished icon to its owner and reject invalid icon dimensions.
 */

$media = $author->call(
    'POST', '/api/media', ['kind' => 'icon'], 201,
    ['name' => 'icon.png', 'type' => 'image/png', 'bytes' => png_fixture(256, 256)]
)['media'];
$media_path = str_replace($config['origin'], '', $media['url']); $guest->call('GET', $media_path, null, 404);
$author->call('GET', $media_path);
$author->call(
    'POST', '/api/media', ['kind' => 'icon'], 422,
    ['name' => 'icon.png', 'type' => 'image/png', 'bytes' => png_fixture(32, 32)]
);
$author->call('PATCH', '/api/submissions/' . $duplicate['id'], ['iconMediaId' => $media['id']]);
