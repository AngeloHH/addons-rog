<?php

/**
 * Addons ROG - Model registry
 *
 * - List the model definitions used to generate the initial database schema.
 * - Keep this order when resolving table dependencies during installation.
 */

// =============================================================================
// INITIAL SCHEMA MODELS
// =============================================================================

return [
    \Rog\Models\Account::class,
    \Rog\Models\AuthToken::class,
    \Rog\Models\Asset::class,
    \Rog\Models\Addon::class,
    \Rog\Models\CommunityRequest::class,
    \Rog\Models\Submission::class,
    \Rog\Models\NameClaim::class,
    \Rog\Models\Comment::class,
    \Rog\Models\Interaction::class,
    \Rog\Models\Report::class,
    \Rog\Models\ReportEntry::class,
    \Rog\Models\Message::class,
    \Rog\Models\AssetLink::class,
    \Rog\Models\Event::class,
    \Rog\Models\Setting::class,
];
