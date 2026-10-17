<?php

/**
 * Addons ROG - Catalog availability after moderation
 *
 * - Verify that public listing and profile endpoints still respond after moderation actions.
 */

foreach (
    [
        '/api/taxonomy', '/api/home', '/api/addons', '/api/addons/' . $slug, '/api/requests', '/api/contact',
        '/api/profiles/AuthorOne'
    ] as $path
) $guest->call('GET', $path);
