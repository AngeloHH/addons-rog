<?php

/**
 * Addons ROG - Runtime configuration
 *
 * - Read database, storage, mail, and security settings from the environment.
 * - Apply private local overrides after the environment defaults.
 */

declare(strict_types=1);

// =============================================================================
// ENVIRONMENT DEFAULTS
// =============================================================================

$config = [
    // Connection details are shared by web requests and console commands.
    'dsn' => getenv('ROG_DSN') ?: '',
    'db_user' => getenv('ROG_DB_USER') ?: '',
    'db_password' => getenv('ROG_DB_PASSWORD') ?: '',
    'db_ssl_ca' => getenv('ROG_DB_SSL_CA') ?: '',
    'create_database' => getenv('ROG_CREATE_DATABASE') !== '0',
    // Signing and setup credentials must be supplied by the deployment.
    'secret' => getenv('ROG_SECRET') ?: '',
    'setup_token' => getenv('ROG_SETUP_TOKEN') ?: '',
    'origin' => rtrim(getenv('ROG_ORIGIN') ?: 'http://127.0.0.1:8080', '/'),
    'storage' => getenv('ROG_STORAGE') ?: dirname(__DIR__) . '/storage',
    'secure_cookie' => getenv('ROG_SECURE_COOKIE') !== '0',
    // Spool delivery is the default until a mail transport is configured.
    'mail_transport' => getenv('ROG_MAIL_TRANSPORT') ?: 'spool',
    'mail_from' => getenv('ROG_MAIL_FROM') ?: 'noreply@example.invalid',
    'smtp_host' => getenv('ROG_SMTP_HOST') ?: '',
    'smtp_port' => (int) (getenv('ROG_SMTP_PORT') ?: 587),
    'smtp_user' => getenv('ROG_SMTP_USER') ?: '',
    'smtp_password' => getenv('ROG_SMTP_PASSWORD') ?: '',
    'trusted_proxies' => array_filter(explode(',', getenv('ROG_TRUSTED_PROXIES') ?: '')),
];

// =============================================================================
// PRIVATE OVERRIDES
// =============================================================================

$local = __DIR__ . '/config.local.php';
if (is_file($local)) $config = array_replace($config, require $local);
return $config;
