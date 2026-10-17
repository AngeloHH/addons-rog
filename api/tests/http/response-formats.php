<?php

/**
 * Addons ROG - Response fixture validation tests
 *
 * - Validate real response values against the test-only schema fixtures.
 * - Support optional properties, nullable values, lists, references, and enums.
 */

// =============================================================================
// SCHEMA INTERPRETATION
// =============================================================================

function schema_check(mixed $value, mixed $schema, array $schemas, string $path): void {
    if (is_array($schema)) {
        check(is_array($value), 'Schema object/array ' . $path);
        if (array_is_list($schema)) {
            check(array_is_list($value), 'Schema array ' . $path);
            foreach ($value as $i => $item) schema_check($item, $schema[0], $schemas, $path . '[' . $i . ']');
        } else foreach ($schema as $key => $child) {
            $name = rtrim($key, '?');
            if (str_ends_with($key, '?') && !array_key_exists($name, $value)) continue;
            check(array_key_exists($name, $value), 'Schema required ' . $path . '.' . $name);
            schema_check($value[$name], $child, $schemas, $path . '.' . $name);
        }
        return;
    }
    // Inline object schemas may contain their own union types.
    if (preg_match('/^(\{.*\})(.*)$/s', $schema, $parts)) {
        if ($value === null && str_contains($parts[2], 'null')) return;
        schema_check($value, json_decode($parts[1], true, 512, JSON_THROW_ON_ERROR), $schemas, $path);
        return;
    }
    $type = trim($schema);
    if ($value === null && preg_match('/(?:^|\|)\s*null\s*(?:\||$)/', $type)) return;
    $type = trim(preg_replace('/\|\s*null\b/', '', $type));
    if (str_ends_with($type, '[]')) {
        check(is_array($value) && array_is_list($value), 'Schema array ' . $path);
        foreach ($value as $i => $item) schema_check($item, substr($type, 0, - 2), $schemas, $path . '[' . $i . ']');
        return;
    }
    // Resolve shared fixture definitions before validating their nested values.
    if (str_starts_with($type, '#')) {
        schema_check($value, $schemas[substr($type, 1)], $schemas, $path);
        return;
    }
    if (str_starts_with($type, "'")) {
        preg_match_all("/'([^']*)'/", $type, $choices);
        check(in_array($value, $choices[1], true), 'Schema enum ' . $path);
        return;
    }
    $valid = match(true) {
        str_starts_with($type, 'string') => is_string($value), $type === 'integer' => is_int($value), $type === 'number'
            => is_int($value) || is_float($value), $type === 'boolean' => is_bool($value), $type === 'true'
                => $value === true, $type === 'false' => $value === false, str_starts_with($type, 'Record<')
                    => is_array($value), default
                        => throw new RuntimeException('Unsupported schema ' . $type . ' at ' . $path),
    };
    check($valid, 'Schema type ' . $path);
    if ($type === 'string<date-time>') check(strtotime($value) !== false, 'Schema date ' . $path);
}

// =============================================================================
// REAL ENDPOINT RESPONSES
// =============================================================================

$schemas = json_decode(
    file_get_contents(dirname(__DIR__) . '/fixtures/response-schemas.json'), true, 512, JSON_THROW_ON_ERROR
);
$viewer = new Client(); $viewer->call('GET', '/api/auth/session');
$listing = $viewer->call('GET', '/api/addons');
foreach ($listing['items'] as $item) schema_check($item, $schemas['AddonSummary'], $schemas, 'AddonSummary');
$detail = $viewer->call('GET', '/api/addons/' . $slug);
foreach ($detail['addon']['versions'] as $item) schema_check($item, $schemas['AddonVersion'], $schemas, 'AddonVersion');
$reviews = $viewer->call('GET', '/api/addons/' . $slug . '/comments');
foreach ($reviews['items'] as $item) schema_check($item, $schemas['Comment'], $schemas, 'Comment');
$requests = $viewer->call('GET', '/api/requests');
foreach ($requests['items'] as $item) schema_check($item, $schemas['Request'], $schemas, 'Request');
$taxonomy = $viewer->call('GET', '/api/taxonomy');
foreach ($taxonomy['expansions'] as $item) schema_check($item, $schemas['Expansion'], $schemas, 'Expansion');
$contact = $viewer->call('GET', '/api/contact');
foreach ($contact['recipients'] as $item)
    schema_check($item, $schemas['ContactRecipient'], $schemas, 'ContactRecipient');
schema_check($second_upload, $schemas['UploadFile'], $schemas, 'UploadFile');
schema_check($second_draft, $schemas['Submission'], $schemas, 'Submission');
schema_check($listing['page'], $schemas['Page'], $schemas, 'Page');
