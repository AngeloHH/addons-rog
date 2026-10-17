<?php

/**
 * Addons ROG - SQL behavior portability tests
 *
 * - Check literal safety, native parameters, JSON semantics, and normalized uniqueness.
 * - Verify submission constraints and daylight-saving calendar boundaries across engines.
 */

use Rog\Database\{Db, Calendar};
use Rog\Models\{Setting, CommunityRequest, Submission};

// =============================================================================
// SQL LITERALS, PARAMETERS, AND JSON VALUES
// =============================================================================

$literal = "FOR UPDATE DESC NULLS LAST :parameter ILIKE -- literal";
check($db->value('SELECT ' . $db->pdo->quote($literal)) === $literal, 'SQL keywords inside literals are preserved');
check(
    (int)$db->value('SELECT CAST(:number AS integer)+CAST(:number AS integer)', ['number' => 7]) === 14,
    'Repeated native placeholders'
);
check((bool)$db->value("SELECT ('{}'::jsonb->'missing') IS NULL"), 'Missing JSON property is SQL null');
check(
    !(bool)$db->value("SELECT ('{\"value\":null}'::jsonb->'value') IS NULL"), 'Explicit JSON null remains a JSON value'
);
check((bool)$db->value("SELECT '{\"n\":1.0}'::jsonb @> '{\"n\":1}'::jsonb"), 'JSON numeric containment');
$merged = json_decode(
    $db->value(
        "SELECT '{\"nested\":{\"old\":1},\"kept\":1}'::jsonb || " . "'{\"nested\":{\"new\":2},\"kept\":null}'::jsonb"
    ),
    true, 512, JSON_THROW_ON_ERROR
);
check(
    array_key_exists('kept', $merged) && $merged['kept'] === null && $merged['nested'] === ['new' => 2],
    'JSON object merge replaces nested values and preserves null'
);

// =============================================================================
// STRING PRIMARY KEYS AND NORMALIZED REQUEST NAMES
// =============================================================================

$ordered = (new Setting($db))->create(['value' => (object) ['number' => 7], 'key' => 'order_independent']);
check(
    $ordered->key === 'order_independent' && $ordered->value->number === 7,
    'String primary key is independent of insertion order'
);
$ordered->delete(); $requests = new CommunityRequest($db);
// Equivalent Unicode and punctuation forms must share a uniqueness key without
// changing display text.
$first = $requests->create(
    [
        'author_id' => $author_id, 'addon_name' => 'Ｐｏｒｔａｂｌｅ Name', 'expansion_id' => 'df',
        'details' => 'Unicode normalization test'
    ]
);
check($first->addon_name === 'Ｐｏｒｔａｂｌｅ Name', 'Unicode display name is preserved');
try {
    $requests->create(
        [
            'author_id' => $author_id, 'addon_name' => 'portable-name', 'expansion_id' => 'df',
            'details' => 'Equivalent name'
        ]
    );
    check(false, 'Equivalent Unicode request rejected');
} catch (PDOException $error) {
    check(Db::unique_violation($error), 'Equivalent Unicode request rejected');
}
$first->state = 'closed'; $first->save();
$second = $requests->create(
    [
        'author_id' => $author_id, 'addon_name' => 'portable-name', 'expansion_id' => 'df',
        'details' => 'Closed names can be reused'
    ]
);
check($second->id !== $first->id, 'Inactive request releases its normalized name');
$second->addon_name = 'Another portable name'; $second->save();
$third = $requests->create(
    [
        'author_id' => $author_id, 'addon_name' => 'portable-name', 'expansion_id' => 'df',
        'details' => 'Renaming updates the normalized key'
    ]
);
check($third->id !== $second->id, 'Renaming a request updates its uniqueness key');
$first->delete(); $second->delete();

// =============================================================================
// SUBMISSION CONSTRAINTS
// =============================================================================

$third->delete(); $submissions = new Submission($db);
expected_exception(
    fn() => $submissions->create(
        ['owner_id' => $author_id, 'kind' => 'addon', 'fields' => (object) ['decision' => null]]
    ),
    PDOException::class, 'Draft cannot contain even a null decision'
);
$pending = [];
foreach (['Portable Alpha', 'Portable Beta'] as $name) $pending[] = $submissions->create(
    [
        'owner_id' => $author_id, 'kind' => 'addon', 'status' => 'pending', 'submitted_at' => gmdate('c'),
        'expansion_id' => 'df', 'version_label' => '1.0',
        'fields' => (object) [
            'name' => $name, 'categoryId' => 'pve', 'summary' => 'Portable pending addon', 'rulesAccepted' => true,
            'testedConfirmed' => true
        ]
    ]
);
check($pending[0]->id !== $pending[1]->id, 'Different pending addons can use the same version label');
foreach ($pending as $item) $item->delete();

// =============================================================================
// DAYLIGHT-SAVING CALENDAR BOUNDARIES
// =============================================================================

$event = $db->value(
    "INSERT INTO events(kind,occurred_at,data) VALUES ('presence','2026-03-09 " . "03:30:00','{}') RETURNING id"
);
$days = Calendar::counts(
    $db, 'events', 'id=:id', ['id' => $event],
    new DateTimeImmutable('2026-03-08', new DateTimeZone('America/New_York')), 2, '+1 day'
);
check(array_map('intval', array_column($days, 'count')) === [1, 0], 'Calendar buckets respect spring DST boundaries');
$db->query('DELETE FROM events WHERE id=:id', ['id' => $event]);
