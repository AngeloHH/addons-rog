<?php

/**
 * Addons ROG - Administration statistics
 *
 * - Compare download, submission, and player activity across reporting periods.
 * - Build live feeds, timezone-aware charts, and community activity records.
 */

declare(strict_types=1);

namespace Rog\Services;

use DateTimeImmutable;
use DateTimeZone;
use Rog\Database\Calendar;
use Rog\Http\Validator as V;

class StatsService extends Service {

    // =========================================================================
    // REPORTING WINDOW AND DASHBOARD COUNTERS
    // =========================================================================

    private function days(): int {
        return (int)rtrim(V::choice($this->request->query['range'] ?? '7d', 'range', ['7d', '30d', '90d']), 'd');
    }

    public function dashboard(): array {
        $days = $this->days(); $params = ['days' => $days]; $kpis = [];
        $metrics = [
            'downloads' => ['counted_downloads', 'occurred_at', 'true'],
            'uploads' => ['submissions', 'decided_at', "status='approved'"],
            'activePlayers' => ['events', 'occurred_at', "kind='presence'"],
        ];
        // Compare the selected period against the equally sized period
        // immediately before it.
        foreach ($metrics as $key => [$table, $date, $where]) {
            $count = $key === 'activePlayers' ? 'count(DISTINCT actor_id)' : 'count(*)';
            $query = "SELECT $count FILTER(WHERE $date>=now()-make_interval(days=>:days))::int AS value,";
            $query .= "$count FILTER(WHERE $date<now()-make_interval(days=>:days))::int AS previous ";
            $query .= "FROM $table WHERE $where AND $date>=now()-make_interval(days=>2*:days)";
            $row = $this->row($query, $params); $kpis[$key] = $row;
        }
        $query = "SELECT count(*) FILTER(WHERE status='pending')::int AS value,";
        $query .= 'count(*) FILTER(WHERE submitted_at<now()-make_interval(days=>:days) ';
        $query .= 'AND (decided_at IS NULL OR decided_at>=now()-make_interval(days=>:days)))::int AS previous,';
        $query .= "coalesce(ceil(max(extract(epoch FROM now()-submitted_at)) FILTER(WHERE status='pending')/3600),0)";
        $query .= '::int AS "oldestWaitHours" FROM submissions';
        $kpis['pendingReview'] = $this->row($query, $params);

        $query = 'SELECT count(*) FILTER(WHERE closed_at IS NULL)::int AS value,';
        $query .= 'count(*) FILTER(WHERE created_at<now()-make_interval(days=>:days) ';
        $query .= 'AND (closed_at IS NULL OR closed_at>=now()-make_interval(days=>:days)))::int AS previous,';
        $query .= "count(*) FILTER(WHERE closed_at IS NULL AND type='file')::int AS critical FROM reports";
        $kpis['openReports'] = $this->row($query, $params);
        return ['kpis' => $kpis];
    }

    // =========================================================================
    // LIVE DOWNLOAD PULSE AND FEED
    // =========================================================================

    public function live(): array {
        $query = "SELECT count(*) FILTER(WHERE occurred_at>now()-interval '1 minute')::int AS minute,";
        $query .= "count(*) FILTER(WHERE occurred_at>=date_trunc('day',now()))::int AS today,";
        $query .= "count(*) FILTER(WHERE occurred_at>=date_trunc('day',now())-interval '7 days' ";
        $query .= "AND occurred_at<now()-interval '7 days')::int AS previous FROM counted_downloads ";
        $query .= "WHERE occurred_at>=date_trunc('day',now())-interval '7 days'";
        $counts = $this->row($query);
        // Include empty minute buckets so the 40-minute pulse stays continuous.
        $pulse = array_column(Calendar::counts(
            $this->db(), 'counted_downloads', 'true', [],
            (new DateTimeImmutable())->setTime((int)gmdate('H'), (int)gmdate('i'))->modify('-39 minutes'),
            40, '+1 minute'
        ), 'count');
        $params = []; $where = 'true';
        if (isset($this->request->query['since'])) {
            $params['since'] = V::date($this->request->query['since'], 'since'); $where = 'e.occurred_at>:since';
        }
        $feed = []; $query = 'SELECT e.*,s.expansion_id,a.slug,a.name,u.username FROM counted_downloads e ';
        $query .= 'JOIN submissions s ON s.id=e.submission_id JOIN addons a ON a.id=s.addon_id ';
        $query .= 'LEFT JOIN accounts u ON u.id=e.actor_id WHERE ' . $where;
        $query .= ' ORDER BY e.occurred_at DESC,e.id DESC LIMIT 8';
        foreach ($this->db()->all($query, $params) as $row) $feed[] = [
            'id' => $this->external_id($row['id']), 'user' => $row['username'] ?? 'Guest',
            'city' => $this->json($row['data'])['city'] ?? null,
            'addon' => [
                'slug' => $row['slug'], 'name' => $row['name'],
                'iconUrl' => $this->app->config['origin'] . '/api/default-icon.svg',
            ],
            'expansionId' => $row['expansion_id'], 'createdAt' => $this->date($row['occurred_at']),
        ];
        return [
            'online' => (int)$this->db()->value(
                "SELECT count(*) FROM accounts WHERE banned_at IS NULL AND last_seen_at>now()-interval '5 minutes'"
            ),
            'perMinute' => $counts['minute'], 'today' => $counts['today'],
            'sameTimeLastWeek' => $counts['previous'], 'pulse' => $pulse, 'feed' => $feed,
        ];
    }

    // =========================================================================
    // TIMEZONE-AWARE DOWNLOAD ANALYSIS
    // =========================================================================

    public function stats(): array {
        $days = $this->days(); $tz = $this->request->query['tz'] ?? 'UTC';
        if (
            !is_string($tz) || !in_array($tz, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)
        ) V::fail('tz');
        $params = ['days' => $days, 'tz' => $tz];
        // Generate both periods using local calendar boundaries before
        // comparing their totals.
        $series = Calendar::counts(
            $this->db(), 'counted_downloads', 'true', [],
            (new DateTimeImmutable('today', new DateTimeZone($tz)))->modify('-' . (2 * $days - 1) . ' days'),
            2 * $days, '+1 day'
        );
        $local = Calendar::local_time($this->db(), $tz, 'occurred_at');
        $localEvent = Calendar::local_time($this->db(), $tz, 'e.occurred_at');
        $current = array_column(array_slice($series, $days), 'count');
        $previous = array_column(array_slice($series, 0, $days), 'count');

        $query = 'SELECT a.id,a.slug,a.name,count(*)::int AS downloads FROM counted_downloads e ';
        $query .= 'JOIN submissions s ON s.id=e.submission_id JOIN addons a ON a.id=s.addon_id ';
        $query .= 'WHERE e.occurred_at>now()-make_interval(days=>:days) GROUP BY a.id ';
        $query .= 'ORDER BY downloads DESC,a.id LIMIT 10';
        $top = $this->db()->all($query, ['days' => $days]);
        // Fill all seven weekdays and 24 hours, then overlay observed download
        // counts.
        $prime = array_fill(0, 7, array_fill(0, 24, 0));
        $query = "SELECT extract(isodow FROM $local)::int-1 AS dow,extract(hour FROM $local)::int AS hour,";
        $query .= 'count(*)::int AS count FROM counted_downloads ';
        $query .= 'WHERE occurred_at>now()-make_interval(days=>:days) GROUP BY 1,2';
        foreach ($this->db()->all($query, $params) as $row) $prime[$row['dow']][$row['hour']] = $row['count'];

        // Compare consecutive downloads by the same signed-in actor to count
        // expansion changes.
        $query = 'WITH history AS (SELECT e.actor_id,s.expansion_id,lag(s.expansion_id) ';
        $query .= 'OVER(PARTITION BY e.actor_id ORDER BY e.occurred_at,e.id) AS previous FROM counted_downloads e ';
        $query .= 'JOIN submissions s ON s.id=e.submission_id WHERE e.actor_id IS NOT NULL ';
        $query .= 'AND e.occurred_at>now()-make_interval(days=>:days)) SELECT previous AS "from",expansion_id AS "to",';
        $query .= 'count(DISTINCT actor_id)::int AS value FROM history WHERE previous IS NOT NULL ';
        $query .= 'AND previous<>expansion_id GROUP BY previous,expansion_id';
        $migration = $this->db()->all($query, ['days' => $days]); $records = [];

        // =====================================================================
        // COMMUNITY ACTIVITY RECORDS
        // =====================================================================

        $query = 'SELECT a.name,a.slug,extract(epoch FROM s.decided_at-s.submitted_at)/3600 AS value ';
        $query .= 'FROM submissions s ';
        $query .= "JOIN addons a ON a.id=s.addon_id WHERE s.status='approved' ORDER BY value,s.id LIMIT 1";
        $fast = $this->db()->one($query);
        if ($fast) $records[] = [
            'id' => 'fastest', 'holder' => ['label' => $fast['name'], 'addonSlug' => $fast['slug']],
            'value' => (float)$fast['value'], 'unit' => 'hours', 'active' => true,
        ];
        $query = 'SELECT a.name,a.slug,f.size_bytes AS value FROM published_versions v ';
        $query .= 'JOIN addons a ON a.id=v.addon_id JOIN assets f ON f.id=v.asset_id ';
        $query .= 'ORDER BY f.size_bytes DESC,v.id LIMIT 1';
        $heavy = $this->db()->one($query);
        if ($heavy) $records[] = [
            'id' => 'heaviest', 'holder' => ['label' => $heavy['name'], 'addonSlug' => $heavy['slug']],
            'value' => (int)$heavy['value'], 'unit' => 'bytes', 'active' => true,
        ];
        // Subtract row positions from distinct dates to identify
        // consecutive-day streaks.
        $query = 'WITH dates AS (SELECT DISTINCT actor_id,occurred_at::date AS day FROM counted_downloads ';
        $query .= 'WHERE actor_id IS NOT NULL),runs AS(SELECT actor_id,';
        $query .= 'extract(epoch FROM CAST(day AS timestamptz))/86400-';
        $query .= '(row_number() OVER(PARTITION BY actor_id ORDER BY day))::int AS grp FROM dates) ';
        $query .= 'SELECT u.username,count(*) AS value FROM runs r JOIN accounts u ON u.id=r.actor_id ';
        $query .= 'GROUP BY r.actor_id,u.username,grp ORDER BY value DESC LIMIT 1';
        $streak = $this->db()->one($query);
        if ($streak) $records[] = [
            'id' => 'streak', 'holder' => ['label' => $streak['username'], 'addonSlug' => null],
            'value' => (int)$streak['value'], 'unit' => 'days', 'active' => true,
        ];
        $query = 'SELECT u.username,count(DISTINCT e.occurred_at::date) AS value FROM counted_downloads e ';
        $query .= 'JOIN accounts u ON u.id=e.actor_id GROUP BY u.id ORDER BY value DESC,u.id LIMIT 1';
        $loyal = $this->db()->one($query);
        if ($loyal) $records[] = [
            'id' => 'loyal', 'holder' => ['label' => $loyal['username'], 'addonSlug' => null],
            'value' => (int)$loyal['value'], 'unit' => 'days', 'active' => true,
        ];
        $query = "SELECT u.username,extract(hour FROM $localEvent)*60+extract(minute FROM $localEvent) AS value ";
        $query .= 'FROM counted_downloads e JOIN accounts u ON u.id=e.actor_id ';
        $query .= "WHERE extract(hour FROM $localEvent)<6 ORDER BY value DESC,e.id DESC LIMIT 1";
        $night = $this->db()->one($query, ['tz' => $tz]);
        if ($night) $records[] = [
            'id' => 'nightOwl', 'holder' => ['label' => $night['username'], 'addonSlug' => null],
            'value' => (int)$night['value'], 'unit' => 'minuteOfDay', 'active' => true,
        ];
        $query = "WITH counts AS(SELECT s.addon_id,count(*) FILTER(WHERE e.occurred_at>now()-interval '30 days')";
        $query .= "::numeric AS recent,count(*) FILTER(WHERE e.occurred_at<=now()-interval '30 days')::numeric ";
        $query .= 'AS previous ';
        $query .= 'FROM counted_downloads e JOIN submissions s ON s.id=e.submission_id ';
        $query .= "WHERE e.occurred_at>now()-interval '60 days' GROUP BY s.addon_id) ";
        $query .= 'SELECT a.name,a.slug,100*(recent-previous)/previous AS value FROM counts c ';
        $query .= 'JOIN addons a ON a.id=c.addon_id WHERE previous>0 AND recent>previous ';
        $query .= 'ORDER BY value DESC,a.id LIMIT 1';
        $comeback = $this->db()->one($query);
        if ($comeback) $records[] = [
            'id' => 'comeback', 'holder' => ['label' => $comeback['name'], 'addonSlug' => $comeback['slug']],
            'value' => (float)$comeback['value'], 'unit' => 'percent', 'active' => true,
        ];
        // Return chart data and records together after all aggregates have been
        // calculated.
        $result = [
            'downloads' => ['from' => $series[$days]['day'], 'current' => $current, 'previous' => $previous],
            'periodTotal' => array_sum($current), 'topAddons' => array_map(fn($r) => [
                'slug' => $r['slug'], 'name' => $r['name'], 'downloads' => $r['downloads'],
                'addonId' => $this->external_id($r['id']), 'downloadCount' => $r['downloads'],
            ], $top),
            'primeTime' => $prime, 'migration' => $migration, 'records' => $records,
        ];
        return $result;
    }
}
