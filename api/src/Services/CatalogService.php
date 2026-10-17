<?php

/**
 * Addons ROG - Public addon catalog
 *
 * - Expose taxonomy, searchable addon listings, version details, and home feeds.
 * - Build public summaries with facet counts and request-local related data caches.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{ApiException, Validator as V, Response};

class CatalogService extends Service {

    // =========================================================================
    // RELATED DATA CACHES AND TAXONOMY
    // =========================================================================

    private array $version_cache = [];
    private array $author_cache = [];
    private array $icon_cache = [];

    public const GROUPS = [
        'category' => ['pve', 'pvp', 'dps', 'healing', 'tank', 'gui', 'map', 'professions'],
        'classes' => [
            'death-knight', 'demon-hunter', 'druid', 'evoker', 'hunter', 'mage', 'monk',
            'paladin', 'priest', 'rogue', 'shaman', 'warlock', 'warrior',
        ],
        'professions' => [
            'alchemy', 'blacksmithing', 'enchanting', 'engineering', 'herbalism',
            'inscription', 'jewelcrafting', 'leatherworking', 'mining', 'skinning',
            'tailoring', 'cooking', 'fishing', 'first-aid', 'archaeology',
        ],
        'role' => ['tank', 'healer', 'dps'],
    ];
    public const EXPANSIONS = [
        'wotlk' => ['Wrath of the Lich King', '3.3.5a'], 'mop' => ['Mists of Pandaria', '5.4.8'],
        'wod' => ['Warlords of Draenor', '6.2.4'], 'bfa' => ['Battle for Azeroth', '8.3.7'],
        'sl' => ['Shadowlands', '9.2.7'], 'df' => ['Dragonflight', '10.2.7'], 'tww' => ['The War Within', '11.0.0'],
    ];

    // =========================================================================
    // PUBLIC SITE METADATA
    // =========================================================================

    public function health(): array { return ['message' => 'API ready', 'php' => PHP_VERSION]; }

    public function default_icon(): Response {
        $response = new Response(null, 200, [
            'Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400',
        ]);
        $response->raw = '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">';
        $response->raw .= '<rect width="256" height="256" rx="32" fill="#182333"/>';
        $response->raw .= '<path d="M64 64h128v128H64z" fill="#75849b"/>'
            . '<path d="M96 96h64v64H96z" fill="#182333"/></svg>';
        return $response;
    }

    /**
     * Combine built-in identifiers, stored display overrides, and published
     * addon counts.
     */
    public function taxonomy(): array {
        $query = 'SELECT expansion_id,count(*)::int AS count FROM latest_versions GROUP BY expansion_id';
        $counts = array_column($this->db()->all($query), 'count', 'expansion_id');
        $overrides = $this->config('taxonomy'); $expansions = [];
        foreach (self::EXPANSIONS as $id => [$name, $patch]) $expansions[] = [
            'id' => $id, 'abbr' => strtoupper($id), 'name' => $overrides['expansions'][$id]['name'] ?? $name,
            'patch' => $overrides['expansions'][$id]['patch'] ?? $patch, 'addonCount' => $counts[$id] ?? 0,
        ];
        $groups = []; $facets = $this->facet_counts([]);
        foreach (self::GROUPS as $group => $ids) {
            $items = [];
            foreach ($ids as $id) $items[] = [
                'id' => $id, 'name' => $overrides['labels'][$id] ?? ucwords(str_replace('-', ' ', $id)),
                'addonCount' => $facets[$group][$id] ?? 0,
            ];
            $groups[] = ['id' => $group, 'items' => $items];
        }
        return [
            'expansions' => $expansions, 'groups' => $groups,
            'limits' => [
                'summaryMax' => 120, 'screenshotsMax' => 4,
                'addonZipMaxBytes' => 52428800, 'imageMaxBytes' => 2097152,
            ],
        ];
    }

    public function site_stats(): array {
        return [
            'addons' => (int)$this->db()->value('SELECT count(DISTINCT addon_id) FROM published_versions'),
            'downloads' => (int)$this->db()->value('SELECT coalesce(sum(downloads),0) FROM catalog_metrics'),
            'players' => (int)$this->db()->value(
                'SELECT count(*) FROM accounts WHERE verified_at IS NOT NULL AND banned_at IS NULL'
            ),
            'averageReviewHours' => (int)round((float)$this->db()->value(
                "SELECT coalesce(avg(extract(epoch FROM decided_at-submitted_at)/3600),0) "
                . "FROM submissions WHERE decided_at>now()-interval '7 days'"
            )),
        ];
    }

    // =========================================================================
    // PUBLISHED ADDON LOOKUP AND CACHE WARMING
    // =========================================================================

    /**
     * Require a visible addon with at least one currently published version.
     */
    public function find(string $slug): array {
        $query = 'SELECT a.*,coalesce(m.downloads,0) AS downloads,m.rating,coalesce(m.votes,0) AS votes ';
        $query .= 'FROM addons a LEFT JOIN catalog_metrics m ON m.addon_id=a.id ';
        $query .= 'WHERE a.slug=:slug AND a.withdrawn_at IS NULL ';
        $query .= 'AND EXISTS(SELECT 1 FROM published_versions v WHERE v.addon_id=a.id)';
        return $this->row($query, ['slug' => $slug]);
    }

    public function versions(int $addon): array {
        $query = 'SELECT v.*,f.filename,f.size_bytes,f.storage_key FROM published_versions v ';
        $query .= 'JOIN assets f ON f.id=v.asset_id WHERE v.addon_id=:id ORDER BY v.released_at DESC,v.id DESC';
        return $this->version_cache[$addon] ??= $this->db()->all($query, ['id' => $addon]);
    }

    /**
     * Load versions, icons, and author references for a whole result page
     * together.
     */
    private function warm(array $addons): void {
        if (!$addons) return;
        $ids = implode(',', array_map('intval', array_column($addons, 'id')));
        foreach ($addons as $addon) {
            $this->version_cache[$addon['id']] = []; $this->icon_cache[$addon['id']] = false;
        }
        $query = 'SELECT v.*,f.filename,f.size_bytes,f.storage_key FROM published_versions v ';
        $query .= 'JOIN assets f ON f.id=v.asset_id WHERE v.addon_id IN (' . $ids . ') ';
        $query .= 'ORDER BY v.released_at DESC,v.id DESC';
        foreach ($this->db()->all($query) as $v) $this->version_cache[$v['addon_id']][] = $v;

        $query = 'SELECT f.*,l.addon_id FROM asset_links l JOIN assets f ON f.id=l.asset_id ';
        $query .= "WHERE l.addon_id IN ($ids) AND l.slot='icon' AND f.status IN ('clean','warning')";
        foreach ($this->db()->all($query) as $icon) $this->icon_cache[$icon['addon_id']] = $icon;

        $query = 'SELECT DISTINCT u.id,u.username,u.profile FROM accounts u ';
        $query .= 'JOIN addons a ON a.owner_id=u.id WHERE a.id IN (' . $ids . ')';
        foreach ($this->db()->all($query) as $u) $this->author_cache[$u['id']] = [
            'id' => $this->external_id($u['id']), 'name' => $u['username'], 'username' => $u['username'],
            'color' => $this->json($u['profile'])['color'] ?? '#75849b',
        ];
    }

    // =========================================================================
    // FACET COUNTS
    // =========================================================================

    private function facet_counts(array $query): array {
        $result = [];
        foreach (['expansion' => array_keys(self::EXPANSIONS)] + self::GROUPS as $group => $ids) {
            // Exclude the current facet group from its own count while
            // retaining the other filters.
            [$where, $params] = $this->filters($group, $query); $join = ''; $field = 'a.category_id';
            if ($group === 'expansion') {
                $join = ' JOIN latest_versions f ON f.addon_id=a.id'; $field = 'f.expansion_id';
            } elseif ($group === 'category') {
                $counts = [];
                foreach ($ids as $i => $id) {
                    $params['primary' . $i] = $id; $params['facet' . $i] = $id;
                    $counts[] = "count(DISTINCT CASE WHEN a.category_id=:primary$i OR (a.details->'categoryIds') "
                        . "@> jsonb_build_array(CAST(:facet$i AS text)) THEN a.id END)::int AS f$i";
                }
                $sql = 'SELECT ' . implode(',', $counts) . ' FROM addons a ';
                $sql .= 'LEFT JOIN catalog_metrics m ON m.addon_id=a.id WHERE ' . $where;
                $row = $this->db()->one($sql, $params);
                $result[$group] = array_combine($ids, array_map('intval', array_values($row))); continue;
            } elseif ($group !== 'category') {
                $key = ['classes' => 'classIds', 'professions' => 'professionIds', 'role' => 'roles'][$group];
                $counts = [];
                foreach ($ids as $i => $id) {
                    $params['facet' . $i] = $id;
                    $counts[] = "count(DISTINCT CASE WHEN (a.details->'$key') "
                        . "@> jsonb_build_array(CAST(:facet$i AS text)) THEN a.id END)::int AS f$i";
                }
                $sql = 'SELECT ' . implode(',', $counts) . ' FROM addons a ';
                $sql .= 'LEFT JOIN catalog_metrics m ON m.addon_id=a.id WHERE ' . $where;
                $row = $this->db()->one($sql, $params);
                $result[$group] = array_combine($ids, array_map('intval', array_values($row))); continue;
            }
            $sql = 'SELECT ' . $field . ' AS id,count(DISTINCT a.id)::int AS count FROM addons a ';
            $sql .= 'LEFT JOIN catalog_metrics m ON m.addon_id=a.id' . $join;
            $sql .= ' WHERE ' . $where . ' GROUP BY ' . $field;
            $rows = $this->db()->all($sql, $params);
            $result[$group] = array_replace(array_fill_keys($ids, 0), array_column($rows, 'count', 'id'));
        }
        return $result;
    }

    // =========================================================================
    // ADDON SUMMARY AND DOWNLOAD REFERENCES
    // =========================================================================

    public function summary(array $addon, array $expansions = []): array {
        $details = $this->json($addon['details']); $versions = $this->versions((int)$addon['id']);
        if (!$versions) throw new ApiException(404, 'NOT_FOUND');
        // Prefer a requested expansion when available; otherwise use the newest
        // published version.
        $selected = $versions[0];
        if ($expansions) foreach ($versions as $version) {
            if (in_array($version['expansion_id'], $expansions, true)) { $selected = $version; break; }
        }
        $compatibility = [];
        foreach ($versions as $version) {
            $id = $version['expansion_id'];
            if (!isset($compatibility[$id])) $compatibility[$id] = [
                'expansionId' => $id, 'latestVersion' => $version['version_label'],
                'latestVersionLabel' => $version['version_label'],
                'latestVersionId' => $this->external_id($version['id']),
                'releasedAt' => $this->date($version['released_at']),
                'updatedAt' => $this->date($version['released_at']),
                'versionCount' => 0,
            ];
            $compatibility[$id]['versionCount']++;
        }
        $query = 'SELECT f.* FROM asset_links l JOIN assets f ON f.id=l.asset_id ';
        $query .= "WHERE l.addon_id=:id AND l.slot='icon' AND f.status IN ('clean','warning')";
        $icon = $this->icon_cache[$addon['id']] ??= $this->db()->one($query, ['id' => $addon['id']]) ?? false;
        $fields = $this->json($selected['fields']);
        $result = [
            'id' => $this->external_id($addon['id']), 'slug' => $addon['slug'], 'name' => $addon['name'],
            'iconUrl' => $icon ? (new FileService($this->app))->url($icon)
                : $this->app->config['origin'] . '/api/default-icon.svg',
            'categoryId' => $addon['category_id'], 'categoryIds' => $details['categoryIds'] ?? [$addon['category_id']],
            'expansionId' => $selected['expansion_id'], 'version' => $selected['version_label'],
            'summary' => $details['summary'] ?? '',
            'rating' => isset($addon['rating']) ? (float)$addon['rating'] : null,
            'votes' => (int)($addon['votes'] ?? 0), 'downloads' => (int)($addon['downloads'] ?? 0),
            'author' => ($this->author_cache[$addon['owner_id']] ??= $this->user_ref($addon['owner_id'])),
            'createdAt' => $this->date($addon['published_at']), 'updatedAt' => $this->date($selected['released_at']),
            'download' => $this->download_ref($addon, $selected),
        ];
        return $result;
    }

    private function download_ref(array $addon, array $version): array {
        return [
            'versionId' => $this->external_id($version['id']),
            'url' => $this->app->config['origin'] . '/api/downloads/' . $version['id'],
            'fileName' => $addon['slug'] . '-' . $version['version_label'] . '-' . $version['expansion_id'] . '.zip',
            'size' => (int)$version['size_bytes'],
        ];
    }

    // =========================================================================
    // SEARCH AND TAXONOMY FILTERS
    // =========================================================================

    private function csv(mixed $value, string $key, array $allowed): array {
        if (!is_string($value)) V::fail($key);
        $values = array_values(array_unique(explode(',', $value)));
        foreach ($values as $item) V::choice($item, $key, $allowed);
        return $values;
    }

    /**
     * Return a SQL predicate and bound values, optionally omitting one facet
     * group.
     */
    public function filters(?string $skip = null, ?array $query = null): array {
        $q = $query ?? $this->request->query; $where = [
            'a.withdrawn_at IS NULL', 'EXISTS(SELECT 1 FROM published_versions p WHERE p.addon_id=a.id)',
        ];
        $params = [];
        foreach (['q', 'author'] as $key) {
            if (isset($q[$key])) {
                $params[$key] = '%' . str_replace(
                    ['\\', '%', '_'], ['\\\\', '\\%', '\\_'], V::text($q[$key], $key, 1, 100)
                ) . '%';
                $where[] = $key === 'q' ? "(a.name ILIKE :q OR a.details::text ILIKE :q)"
                    : "EXISTS(SELECT 1 FROM accounts u WHERE u.id=a.owner_id AND u.username ILIKE :author)";
            }
        }
        foreach (['expansion' => array_keys(self::EXPANSIONS)] + self::GROUPS as $group => $allowed) {
            if (!isset($q[$group]) || $skip === $group) continue;
            $values = $this->csv($q[$group], $group, $allowed); $parts = [];
            foreach ($values as $i => $value) {
                $param = $group . $i; $params[$param] = $value;
                if ($group === 'category') $params[$param . 'extra'] = $value;
                $parts[] = match($group) {
                    'expansion' => "EXISTS(SELECT 1 FROM published_versions v "
                        . "WHERE v.addon_id=a.id AND v.expansion_id=:$param)",
                    'category' => "(a.category_id=:$param OR (a.details->'categoryIds') "
                        . "@> jsonb_build_array(CAST(:{$param}extra AS text)))",
                    default => "(a.details->'"
                        . (['classes' => 'classIds', 'professions' => 'professionIds', 'role' => 'roles'][$group])
                        . "') @> jsonb_build_array(CAST(:$param AS text))",
                };
            }
            // Values within one group are alternatives; separate groups are
            // combined with AND.
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        if (isset($q['ratingMin']) || isset($q['rating'])) {
            $value = (string)($q['ratingMin'] ?? $q['rating']); V::choice($value, 'rating', ['0', '3', '4', '4.5']);
            if ($skip !== 'rating') {
                $where[] = 'coalesce(m.rating,0)>=CAST(:rating AS numeric)'; $params['rating'] = $value;
            }
        }
        if (isset($q['recent'])) {
            V::choice((string)$q['recent'], 'recent', ['0', '1', 'true', 'false']);
            if (in_array((string)$q['recent'], ['1', 'true'], true)) $where[] =
                "EXISTS(SELECT 1 FROM published_versions v WHERE v.addon_id=a.id "
                . "AND v.released_at>now()-interval '30 days')";
        }
        return [implode(' AND ', $where), $params];
    }

    // =========================================================================
    // CATALOG LISTING AND ADDON DETAIL
    // =========================================================================

    public function listing(): Response {
        [$limit, $offset] = $this->pagination(12, 48); [$where, $params] = $this->filters();
        $sort = V::choice(
            $this->request->query['sort'] ?? 'downloads', 'sort',
            ['downloads', 'rating', 'recent', 'newest', 'updated', 'az', 'name']
        );
        $order = match($sort) {
            'rating' => 'm.rating DESC NULLS LAST', 'recent', 'newest' => 'a.published_at DESC',
            'updated' => '(SELECT max(released_at) FROM published_versions WHERE addon_id=a.id) DESC',
            'az', 'name' => 'lower(a.name)', default => 'coalesce(m.downloads,0) DESC',
        };
        $base = ' FROM addons a LEFT JOIN catalog_metrics m ON m.addon_id=a.id WHERE ' . $where;
        $total = (int)$this->db()->value('SELECT count(*)' . $base, $params);
        $query = 'SELECT a.*,m.downloads,m.rating,m.votes' . $base;
        $query .= ' ORDER BY ' . $order . ',a.id LIMIT :limit OFFSET :offset';
        $rows = $this->db()->all($query, $params + ['limit' => $limit, 'offset' => $offset]);
        $exp = isset($this->request->query['expansion']) ? explode(',', $this->request->query['expansion']) : [];
        $this->warm($rows); $facets = $this->facet_counts($this->request->query);
        return $this->page(array_map(fn($a) => $this->summary($a, $exp), $rows), $total, $offset, $limit, [
            'facets' => $facets,
        ]);
    }

    /**
     * Expand the summary with published versions, screenshots, compatibility,
     * and viewer state.
     */
    public function show(): array {
        $addon = $this->find($this->request->param('slug')); $details = $this->json($addon['details']);
        $exp = $this->request->query['expansion'] ?? null;
        if ($exp !== null) $this->expansion($exp);
        $summary = $this->summary($addon, $exp ? [$exp] : []);
        $rows = $this->versions((int)$addon['id']); $versions = []; $compat = [];
        foreach ($rows as $i => $v) {
            $fields = $this->json($v['fields']); $eid = $v['expansion_id']; $latest = !isset($compat[$eid]);
            if ($latest) $compat[$eid] = [
                'expansionId' => $eid, 'latestVersion' => $v['version_label'],
                'latestVersionId' => $this->external_id($v['id']),
                'releasedAt' => $this->date($v['released_at']), 'versionCount' => 0,
            ];
            $compat[$eid]['versionCount']++;
            $query = 'SELECT count(*) FROM counted_downloads WHERE submission_id=:id';
            $downloads = (int)$this->db()->value($query, ['id' => $v['id']]);
            $versions[] = [
                'id' => $this->external_id($v['id']),
                'number' => $v['version_label'], 'versionLabel' => $v['version_label'],
                'addonId' => $this->external_id($addon['id']), 'title' => $fields['title'] ?? $v['version_label'],
                'notes' => $fields['changelog'] ?? '', 'changelog' => $fields['changelog'] ?? '',
                'expansionId' => $eid, 'clientPatch' => $fields['clientPatch'] ?? self::EXPANSIONS[$eid][1],
                'size' => (int)$v['size_bytes'], 'fileSizeBytes' => (int)$v['size_bytes'], 'fileName' => $v['filename'],
                'downloads' => $downloads, 'downloadCount' => $downloads, 'author' => $this->user_ref($v['owner_id']),
                'releasedAt' => $this->date($v['released_at']), 'publishedAt' => $this->date($v['released_at']),
                'isLatest' => $i === 0, 'isLatestForExpansion' => $latest,
                'download' => $this->download_ref($addon, $v),
            ];
        }
        $query = 'SELECT f.*,l.position FROM asset_links l JOIN assets f ON f.id=l.asset_id ';
        $query .= "WHERE l.addon_id=:id AND l.slot='screenshot' AND f.status IN ('clean','warning') ";
        $query .= 'ORDER BY l.position';
        $media = $this->db()->all($query, ['id' => $addon['id']]);
        $screens = array_map(fn($a) => (new FileService($this->app))->url($a), $media);
        $rating = $this->db()->value('SELECT rating FROM current_ratings WHERE addon_id=:addon AND author_id=:user', [
            'addon' => $addon['id'], 'user' => $this->user_id(),
        ]);
        $viewer = [
            'isFavorite' => (bool)$this->db()->value(
                "SELECT EXISTS(SELECT 1 FROM interactions WHERE kind='favorite' "
                . "AND addon_id=:addon AND actor_id=:user)",
                ['addon' => $addon['id'], 'user' => $this->user_id()]
            ),
            'rating' => $rating === false ? null : (int)$rating,
        ];
        $full = $summary + [
            'canonicalExpansionId' => $rows[0]['expansion_id'], 'description' => $details['description'] ?? '',
            'features' => $details['features'] ?? [], 'screenshots' => $screens,
            'classIds' => $details['classIds'] ?? [],
            'professionIds' => $details['professionIds'] ?? [], 'roles' => $details['roles'] ?? [],
            'tags' => $details['tags'] ?? [], 'languages' => $details['languages'] ?? [],
            'licence' => $details['licence'] ?? null, 'originalAuthor' => $details['originalAuthor'] ?? null,
            'sourceUrl' => $details['sourceUrl'] ?? null,
            'compatibility' => array_values($compat), 'versions' => $versions,
            'commentCount' => (int)$this->db()->value(
                'SELECT count(*) FROM current_ratings WHERE addon_id=:id AND withdrawn_at IS NULL',
                ['id' => $addon['id']]
            ),
            'viewer' => $viewer,
        ];
        return ['addon' => $full];
    }

    // =========================================================================
    // NAME AVAILABILITY AND FAVORITES
    // =========================================================================

    /**
     * Resolve normalized name claims, including pending submissions and
     * optional version conflicts.
     */
    public function check_name(): array {
        $name = V::text($this->request->query['name'] ?? null, 'name', 1, 120);
        $key = $this->db()->value('SELECT normalize_name(:name)', ['name' => $name]);
        $query = 'SELECT n.*,a.slug,a.name,a.category_id,s.fields,s.expansion_id FROM name_claims n ';
        $query .= 'LEFT JOIN addons a ON a.id=n.addon_id ';
        $query .= 'LEFT JOIN submissions s ON s.id=n.submission_id WHERE n.key=:key';
        $claim = $this->db()->one($query, ['key' => $key]); $existing = null; $version_exists = null;
        if ($claim) {
            $fields = $this->json($claim['fields'] ?? '{}');
            $query = 'SELECT expansion_id FROM published_versions WHERE addon_id=:id ';
            $query .= 'ORDER BY released_at DESC,id DESC LIMIT 1';
            $latest = $claim['addon_id'] ? $this->db()->one($query, ['id' => $claim['addon_id']]) : null;
            $existing = [
                'slug' => $claim['slug'], 'name' => $claim['name'] ?? $fields['name'] ?? $name,
                'expansionId' => $latest['expansion_id'] ?? $claim['expansion_id'],
                'categoryId' => $claim['category_id'] ?? $fields['categoryId'] ?? null,
                'match' => ($claim['slug'] !== null && strtolower($name) === $claim['slug'] ? 'slug'
                    : (str_contains($claim['kinds'], 'alias') ? 'alias' : 'name')),
                'pending' => $claim['submission_id'] !== null,
            ];
            if ($claim['addon_id'] && isset($this->request->query['expansion'], $this->request->query['version'])) {
                $query = 'SELECT EXISTS(SELECT 1 FROM submissions WHERE addon_id=:id AND expansion_id=:expansion ';
                $query .= "AND version_label=:version AND status IN ('pending','approved'))";
                $version_exists = (bool)$this->db()->value($query, [
                    'id' => $claim['addon_id'], 'expansion' => $this->expansion($this->request->query['expansion']),
                    'version' => V::text($this->request->query['version'], 'version', 1, 60),
                ]);
            }
        }
        return ['available' => $claim === null, 'existing' => $existing, 'versionExists' => $version_exists];
    }

    /**
     * Apply the requested favorite state without creating duplicate interaction
     * rows.
     */
    public function favorite(): array {
        $addon = $this->find($this->request->param('slug'));
        $active = V::boolean($this->request->input('favorited'), 'favorited');
        if ($active) $this->db()->query(
            "INSERT INTO interactions(actor_id,kind,addon_id) VALUES (:user,'favorite',:addon) ON CONFLICT DO NOTHING",
            ['user' => $this->user_id(), 'addon' => $addon['id']]
        );
        else $this->db()->query(
            "DELETE FROM interactions WHERE actor_id=:user AND kind='favorite' AND addon_id=:addon",
            ['user' => $this->user_id(), 'addon' => $addon['id']]
        );
        return ['favorited' => $active];
    }

    // =========================================================================
    // HOME PAGE FEEDS
    // =========================================================================

    public function home(): array {
        $limit = V::integer($this->request->query['limit'] ?? 6, 'limit', 1, 12);
        $base = 'SELECT a.*,m.downloads,m.rating,m.votes FROM addons a LEFT JOIN catalog_metrics m ON m.addon_id=a.id ';
        $base .= 'WHERE a.withdrawn_at IS NULL AND EXISTS(SELECT 1 FROM published_versions v WHERE v.addon_id=a.id)';
        $latest = array_map(fn($a) => $this->summary($a), $this->db()->all(
            $base . ' ORDER BY a.published_at DESC,a.id DESC LIMIT :limit', ['limit' => $limit]
        ));
        $updated = []; $query = $base . ' AND EXISTS(SELECT 1 FROM published_versions v ';
        $query .= "JOIN submissions s ON s.id=v.id WHERE v.addon_id=a.id AND s.kind='version') ";
        $query .= 'ORDER BY (SELECT max(released_at) FROM published_versions WHERE addon_id=a.id) ';
        $query .= 'DESC,a.id DESC LIMIT :limit';
        foreach ($this->db()->all($query, ['limit' => $limit]) as $a) {
            $item = $this->summary($a); $versions = $this->versions((int)$a['id']);
            $same = array_values(array_filter($versions, fn($v) => $v['expansion_id'] === $item['expansionId']));
            $item += [
                'previousVersion' => $same[1]['version_label'] ?? null,
                'changelog' => $this->json($same[0]['fields'])['changelog'] ?? '',
            ];
            $updated[] = $item;
        }
        $packs = (new DownloadService($this->app))->packs();
        return ['latest' => $latest, 'updated' => $updated, 'packs' => $packs];
    }
}
