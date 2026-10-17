<?php

/**
 * Addons ROG - Local configuration example
 *
 * - Copy the required settings into the private local configuration file.
 * - Choose a database DSN and replace the example credentials before use.
 */

// =============================================================================
// DATABASE AND DEPLOYMENT SETTINGS
// =============================================================================

return [
    'dsn'=>'pgsql:host=127.0.0.1;port=5432;dbname=rog',
    // Alternatives: mysql:host=127.0.0.1;port=3306;dbname=rog;charset=utf8mb4
    //               sqlite:/absolute/private/path/rog.sqlite
    'db_user'=>'rog_app',
    'db_password'=>'replace-me',
    'db_ssl_ca'=>'',         // MySQL: optional CA file enables verified TLS.
    'create_database'=>true, // Explicit initial installation only, with creation permission.
    'secret'=>'',            // Generate a random key of at least 32 characters.
    'origin'=>'https://your-domain.example',
    'storage'=>dirname(__DIR__).'/storage',
    'mail_transport'=>'spool',
];
