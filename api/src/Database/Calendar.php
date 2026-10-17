<?php

/**
 * Addons ROG - Calendar-aware database counts
 *
 * - Build timezone expressions for each supported database engine.
 * - Count events in local calendar periods using UTC query boundaries.
 */

declare(strict_types=1);

namespace Rog\Database;

use DateTimeImmutable;
use DateTimeZone;

final class Calendar {

    // =========================================================================
    // LOCAL TIME EXPRESSIONS
    // =========================================================================

    public static function local_time(Db $db, string $timezone, string $column): string {
        $zone = new DateTimeZone($timezone); $quoted = $db->pdo->quote($timezone);
        if ($db->driver === 'pgsql') return "($column AT TIME ZONE $quoted)";
        if ($db->driver === 'sqlite') return "rog_timezone($quoted,$column)";

        $query = 'SELECT min(occurred_at) AS first, max(occurred_at) AS last ';
        $bounds = $db->one($query . 'FROM counted_downloads');
        $start = new DateTimeImmutable($bounds['first'] ?? 'now');
        $end = new DateTimeImmutable($bounds['last'] ?? 'now');

        // Expand timezone transitions for MySQL without relying on installed timezone tables.
        $transitions = $zone->getTransitions($start->getTimestamp(), $end->getTimestamp()+1);
        $offset = (int) ($transitions[0]['offset'] ?? $zone->getOffset($start)); $cases = [];

        foreach (array_slice($transitions ?: [], 1) as $transition) {
            $date = gmdate('Y-m-d H:i:s', $transition['ts']);
            $cases[] = "WHEN $column < '$date' THEN $offset";
            $offset = (int) $transition['offset'];
        }

        $offset = $cases ? 'CASE ' . implode(' ', $cases) . " ELSE $offset END" : $offset;
        return "timestampadd(SECOND,$offset,$column)";
    }

    // =========================================================================
    // CALENDAR PERIOD COUNTS
    // =========================================================================

    public static function counts(
        Db $db, string $table, string $where, array $params,
        DateTimeImmutable $start, int $count, string $step, string $label = 'day'
    ): array {
        Db::identifier($table); $selects = [];

        // Advance in the local timezone so daylight-saving changes preserve calendar boundaries.
        for ($i = 0; $i < $count; $i++) {
            $end = $start->modify($step);
            $params['start' . $i] = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            $params['end' . $i] = $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            $params['label' . $i] = $start->format('Y-m-d');

            $query = "SELECT :label$i AS " . Db::identifier($label) . ', ';
            $query .= "(SELECT count(*) FROM $table WHERE $where ";
            $query .= "AND occurred_at >= :start$i AND occurred_at < :end$i) AS count";
            $selects[] = $query; $start = $end;
        }

        return $db->all(implode(' UNION ALL ', $selects), $params);
    }
}