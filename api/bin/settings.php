<?php

/**
 * Addons ROG - Setting import command
 *
 * - Validate a JSON object before storing it under a setting key.
 * - Update an existing setting or create it when the key is missing.
 */

declare(strict_types=1);

use Rog\Database\Db;
use Rog\Http\Validator;
use Rog\Models\Setting;

// =============================================================================
// CONFIGURATION AND INPUT VALIDATION
// =============================================================================

$config = require dirname(__DIR__) . '/bootstrap.php';
$key = Validator::text($argv[1] ?? null, 'key', 1, 100); $path = $argv[2] ?? null;

$error = 'Usage: php bin/settings.php key object.json';
if (!$path || !is_file($path)) throw new RuntimeException($error);

// Decode as an object so JSON lists and scalar values are rejected.
$value = json_decode(file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
$error = 'The setting must be a JSON object';
if (!$value instanceof stdClass) throw new RuntimeException($error);

// =============================================================================
// SETTING PERSISTENCE
// =============================================================================

$db = new Db($config); $settings = new Setting($db);
$setting = $settings->get($key);
if ($setting) {
    $setting->value = $value; $setting->updated_at = gmdate('c');
    $setting->save();
} else $settings->create(['key' => $key, 'value' => $value]);

echo "Setting saved\n";
