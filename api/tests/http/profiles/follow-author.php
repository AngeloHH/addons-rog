<?php

/**
 * Addons ROG - Author follow tests
 *
 * - Set the authenticated viewer as a follower of the published addon author.
 */

$other->call('PUT', '/api/users/AuthorOne/follow', ['following' => true]);
check($other->call('GET', '/api/profiles/AuthorOne')['viewer']['following'], 'Profile follow survives a fresh read');
