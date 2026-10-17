<?php

/**
 * Addons ROG - Addon ZIP structural scan
 *
 * - Inspect archive paths, sizes, file types, and addon TOC metadata.
 * - Return a content hash, detected expansion, warnings, and scan status.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\ApiException;
use ZipArchive;

class ZipScanner {
    /**
     * Inspect ZIP members without extracting files into the application
     * filesystem.
     */
    public function scan(string $path): array {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) throw new ApiException(422, 'INVALID_ARCHIVE');
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > 10000) throw new ApiException(422, 'INVALID_ARCHIVE');
            $total = 0; $folders = []; $interfaces = []; $warnings = []; $toc = 0; $seen = [];

            // =================================================================
            // ARCHIVE MEMBER VALIDATION
            // =================================================================

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); $name = $stat['name'];
                if (($stat['encryption_method'] ?? 0) !== 0) throw new ApiException(415, 'UNSUPPORTED_MEDIA_TYPE');
                if (
                    str_contains($name, "\0") || str_contains($name, '\\')
                    || preg_match('~(^/|^[A-Za-z]:|(^|/)\.\.?(/|$))~', $name) || strlen($name) > 500
                ) throw new ApiException(422, 'INVALID_ARCHIVE');
                // Reject symlinks and case-insensitive duplicate paths before
                // examining file metadata.
                $opsys = 0; $attributes = 0; $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if (($attributes >> 16 & 0170000) === 0120000) throw new ApiException(422, 'INVALID_ARCHIVE');
                if (isset($seen[strtolower($name)])) throw new ApiException(422, 'INVALID_ARCHIVE');
                $seen[strtolower($name)] = true; $total += $stat['size'];
                if (
                    $total > 250 * 1024 * 1024 || $stat['size'] > 50 * 1024 * 1024
                    || $stat['size'] > max(1048576, $stat['comp_size'] * 200)
                ) throw new ApiException(422, 'INVALID_ARCHIVE');
                // Track top-level addon folders after bounding member and total
                // uncompressed sizes.
                if (str_contains($name, '/')) $folders[explode('/', $name)[0]] = true;
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($extension, [
                    'exe', 'dll', 'com', 'bat', 'cmd', 'msi', 'scr', 'ps1',
                    'vbs', 'js', 'jar', 'sh', 'php', 'phtml', 'so', 'dylib',
                ], true)) $warnings[] = [
                    'code' => 'EXECUTABLE_FOUND', 'severity' => 'danger',
                    'path' => $name, 'message' => 'Executable content',
                ];
                // Read bounded TOC files to collect interface versions and flag
                // nested addon folders.
                if ($extension === 'toc') {
                    $toc++;
                    if ($stat['size'] > 1048576) throw new ApiException(422, 'INVALID_ARCHIVE');
                    $contents = $zip->getFromIndex($i);
                    if ($contents === false) throw new ApiException(422, 'INVALID_ARCHIVE');
                    preg_match_all('/^##\s*Interface\s*:\s*([0-9, ]+)/mi', $contents, $matches);
                    foreach ($matches[1] as $values) foreach (preg_split('/[, ]+/', trim($values)) as $value) {
                        if (ctype_digit($value)) $interfaces[] = (int)$value;
                    }
                    if (substr_count($name, '/') > 1) $warnings[] = [
                        'code' => 'NESTED_FOLDER', 'severity' => 'warning',
                        'path' => $name, 'message' => 'TOC is nested below addon folder',
                    ];
                }
                if (stripos($name, 'SavedVariables/') !== false) $warnings[] = [
                    'code' => 'SAVED_VARIABLES', 'severity' => 'warning',
                    'path' => $name, 'message' => 'Saved variables included',
                ];
            }

            // =================================================================
            // SCAN SUMMARY AND EXPANSION DETECTION
            // =================================================================

            if ($toc === 0) $warnings[] = [
                'code' => 'MISSING_TOC', 'severity' => 'warning', 'path' => null, 'message' => 'No TOC file',
            ];
            $interfaces = array_values(array_unique($interfaces));
            $expansions = array_values(array_unique(array_filter(array_map(fn($i) => match((int)floor($i / 10000)) {
                3 => 'wotlk', 5 => 'mop', 6 => 'wod', 8 => 'bfa', 9 => 'sl', 10 => 'df', 11 => 'tww', default => null,
            }, $interfaces))));
            return [
                'sha256' => hash_file('sha256', $path), 'folders' => array_keys($folders),
                'tocInterfaces' => $interfaces,
                'detectedExpansionId' => count($expansions) === 1 ? $expansions[0] : null, 'warnings' => $warnings,
                'status' => in_array('danger', array_column($warnings, 'severity'), true)
                    ? 'blocked' : ($warnings ? 'warning' : 'clean'),
            ];
        } finally { $zip->close(); }
    }
}
