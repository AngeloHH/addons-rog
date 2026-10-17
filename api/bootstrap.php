<?php

/**
 * Addons ROG - Runtime bootstrap
 *
 * - Use UTC timestamps and autoload classes from the Rog namespace.
 * - Return the effective configuration to web and console entry points.
 */

declare(strict_types=1);

// =============================================================================
// TIMEZONE AND CLASS LOADING
// =============================================================================

date_default_timezone_set('UTC');
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'Rog\\')) return;
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});
// Load configuration after the autoloader is available.
return require __DIR__ . '/config/config.php';
