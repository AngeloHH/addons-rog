<?php

/**
 * Addons ROG - Synthetic addon archives
 *
 * - Generate 30 distinct addons with one to five versions and valid TOC/Lua files.
 * - Include deterministic text, upload images, checksums, and a reusable manifest.
 */

declare(strict_types=1);

use Rog\Services\{CatalogService, ZipScanner};

// =============================================================================
// OUTPUT AND REPRODUCIBLE SAMPLE DATA
// =============================================================================

require dirname(__DIR__, 2) . '/bootstrap.php';
$root = dirname(__DIR__, 3);
$output = $argv[1] ?? $root . '/.test-data/addons-' . gmdate('Ymd-His');
$seed = filter_var($argv[2] ?? '20261017', FILTER_VALIDATE_INT);
if ($seed === false) throw new RuntimeException('The seed must be an integer');
if (file_exists($output)) throw new RuntimeException('Choose a new output directory; existing files are preserved');
if (!mkdir($output, 0700, true)) throw new RuntimeException('Cannot create fixture directory');
$output = realpath($output); mt_srand($seed);

$names = [
    'Arcane Compass', 'Bronze Ledger', 'Crystal Beacon', 'Dusk Tracker', 'Ember Atlas',
    'Frost Notes', 'Golden Anchor', 'Hidden Grove', 'Ivory Journal', 'Jade Counter',
    'Kings Watch', 'Lunar Panel', 'Mystic Routes', 'Northern Signal', 'Onyx Tools',
    'Prism Frames', 'Quiet Forge', 'Ruby Markers', 'Silver Pouch', 'Twilight Guide',
    'Umber Runes', 'Velvet Meter', 'Wild Lantern', 'Xenon Board', 'Yellow Quiver',
    'Zephyr Bags', 'Amber Focus', 'Cobalt Talents', 'Emerald Alerts', 'Scarlet Trades',
];
$interfaces = [30300, 50400, 60200, 80300, 90200, 100200, 110000];
$expansions = array_keys(CatalogService::EXPANSIONS);
$categories = CatalogService::GROUPS['category'];
$counts = array_merge(...array_fill(0, 6, range(1, 5))); shuffle($counts);
$manifest = ['seed' => $seed, 'synthetic' => true, 'addonCount' => 30, 'versionCount' => 0, 'addons' => []];

// =============================================================================
// RANDOM TEXT AND VALID UPLOAD IMAGES
// =============================================================================

function sample_text(int $length): string {
    $words = explode(' ', 'arcane bronze crystal dusk ember frost golden hidden ivory jade lunar mystic '
        . 'northern onyx prism quiet ruby silver twilight umber velvet wild amber cobalt emerald scarlet');
    $text = [];
    for ($i = 0; $i < $length; $i++) $text[] = $words[mt_rand(0, count($words) - 1)];
    return implode(' ', $text);
}

function sample_png(int $width, int $height): string {
    $chunk = fn($type, $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $color = [mt_rand(35, 200), mt_rand(35, 200), mt_rand(35, 200)]; $raw = '';
    for ($row = 0; $row < $height; $row++) {
        $pixel = chr(($color[0] + intdiv($row, 4)) % 256);
        $pixel .= chr(($color[1] + intdiv($row, 6)) % 256) . chr($color[2]);
        $raw .= "\0" . str_repeat($pixel, $width);
    }
    return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', '');
}

function save_sample(string $path, string $data): void {
    if (file_put_contents($path, $data) !== strlen($data)) throw new RuntimeException('Cannot write fixture file');
}

// =============================================================================
// ARCHIVES, MATCHING METADATA, AND FOUR SCREENSHOTS
// =============================================================================

foreach ($names as $index => $title) {
    $folder = 'RogTest' . str_replace(' ', '', $title); $name = 'ROG Test ' . $title;
    $directory = $output . '/' . $folder;
    if (!mkdir($directory . '/images', 0700, true)) throw new RuntimeException('Cannot create addon directory');
    $expansion = $expansions[$index % count($expansions)];
    $addon = [
        'name' => $name, 'folder' => $folder, 'expansionId' => $expansion,
        'categoryId' => $categories[$index % count($categories)],
        'summary' => 'Synthetic upload fixture: ' . $title . '.',
        'description' => 'Generated test data for uploads, moderation, versions and downloads. '
            . 'These files are not real gameplay addons.\n\n' . sample_text(60),
        'tags' => ['synthetic', 'upload-test'], 'originalAuthor' => 'ROG Fixture Generator',
        'icon' => $folder . '/images/icon.png', 'screenshots' => [], 'versions' => [],
    ];
    save_sample($directory . '/images/icon.png', sample_png(256, 256));
    for ($screen = 1; $screen <= 4; $screen++) {
        $path = $folder . '/images/screenshot-' . $screen . '.png';
        save_sample($output . '/' . $path, sample_png(640, 360)); $addon['screenshots'][] = $path;
    }
    for ($release = 0; $release < $counts[$index]; $release++) {
        $version = '1.' . $release . '.0'; $interface = $interfaces[$index % count($interfaces)];
        $filename = $folder . '-' . $version . '-' . $expansion . '.zip';
        $files = [
            $folder . '.toc' => "## Interface: $interface\n## Title: $name\n## Version: $version\n"
                . "## Author: ROG Fixture Generator\n## Notes: Synthetic upload fixture\n"
                . "Core.lua\nData.lua\nLocales/enUS.lua\n",
            'Core.lua' => "-- Synthetic fixture; no gameplay behavior.\nlocal name, addon = ...\n"
                . 'addon.version = "' . $version . '"' . "\n-- " . sample_text(32) . "\n",
            'Data.lua' => "-- Random sample data.\nlocal samples = {\n",
            'Locales/enUS.lua' => '-- Synthetic locale: ' . sample_text(48) . "\nlocal locale = \"enUS\"\n",
            'README.txt' => "SYNTHETIC TEST ADDON\n$name $version\n\n" . sample_text(200) . "\n",
        ];
        for ($line = 0; $line < 12; $line++) $files['Data.lua'] .= '    "' . sample_text(8) . '",' . "\n";
        $files['Data.lua'] .= "}\n";
        for ($extra = 1; $extra <= mt_rand(2, 5); $extra++) {
            $files['notes/sample-' . $extra . '.txt'] = sample_text(mt_rand(100, 300)) . "\n";
        }
        $zip = new ZipArchive();
        if ($zip->open($directory . '/' . $filename, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Cannot create fixture ZIP');
        }
        foreach ($files as $path => $contents) {
            $member = $folder . '/' . $path;
            if (!$zip->addFromString($member, $contents)) throw new RuntimeException('Cannot add fixture member');
            $zip->setMtimeName($member, 1700000000);
        }
        if (!$zip->close()) throw new RuntimeException('Cannot finish fixture ZIP');
        $scan = (new ZipScanner())->scan($directory . '/' . $filename);
        if ($scan['status'] !== 'clean' || $scan['detectedExpansionId'] !== $expansion) {
            throw new RuntimeException('Generated ZIP failed structural validation');
        }
        $addon['versions'][] = [
            'version' => $version, 'file' => $folder . '/' . $filename, 'sha256' => $scan['sha256'],
            'files' => count($files), 'changelog' => 'Synthetic release ' . $version . ': ' . sample_text(20),
        ];
        $manifest['versionCount']++;
    }
    $manifest['addons'][] = $addon;
}

// =============================================================================
// MANIFEST AND GENERATION SUMMARY
// =============================================================================

save_sample($output . '/manifest.json', json_encode(
    $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
) . "\n");
echo json_encode([
    'output' => $output, 'seed' => $seed, 'addons' => 30, 'versions' => $manifest['versionCount'],
    'versionsPerAddon' => ['min' => min($counts), 'max' => max($counts)], 'scan' => 'all clean',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
