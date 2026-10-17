<?php

/**
 * Addons ROG - Submission and moderation lifecycle
 *
 * - Validate drafts, link owned uploads, and submit complete addon versions.
 * - Manage review queues, name claims, publication, and owner notifications.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{ApiException, Validator as V, Response};
use Rog\Models\NameClaim;

class SubmissionService extends Service {

    // =========================================================================
    // OWNERSHIP AND SUBMISSION PRESENTATION
    // =========================================================================

    private function submission_id(): int {
        return V::integer($this->request->params['submissionId'] ?? null, 'submissionId');
    }

    /**
     * Restrict lookup to the current owner and reject drafts beyond their
     * retention window.
     */
    private function owned(bool $lock = false): array {
        $query = 'SELECT * FROM submissions WHERE id=:id AND owner_id=:owner' . ($lock ? ' FOR UPDATE' : '');
        $row = $this->row($query, ['id' => $this->submission_id(), 'owner' => $this->user_id()]);
        if (
            $row['status'] === 'draft' && strtotime($row['updated_at']) < time() - 2592000
        ) throw new ApiException(410, 'DRAFT_EXPIRED');
        return $row;
    }

    public function present(array $row): array {
        $fields = $this->json($row['fields']); $files = new FileService($this->app);
        $upload = null; $icon = null; $screens = [];
        foreach ($files->linked((int)$row['id']) as $asset) {
            $p = $files->present($asset);
            if ($asset['slot'] === 'zip') $upload = $p;
            elseif ($asset['slot'] === 'icon') $icon = $p;
            else $screens[] = $p;
        }
        $slug = $row['addon_id'] ? $this->db()->value(
            'SELECT slug FROM addons WHERE id=:id', ['id' => $row['addon_id']]
        ) : null;
        $expiry = $row['status'] === 'draft' ? gmdate('c', strtotime($row['updated_at']) + 2592000) : null;
        return [
            'id' => (int)$row['id'], 'status' => $row['status'], 'kind' => $row['kind'],
            'revision' => (int)$row['revision'], 'addonSlug' => $slug,
            'requestId' => $row['request_id'] === null ? null : (int)$row['request_id'],
            'upload' => $upload, 'name' => $fields['name'] ?? null, 'version' => $row['version_label'],
            'categoryId' => $fields['categoryId'] ?? null,
            'categoryIds' => $fields['categoryIds'] ?? (empty($fields['categoryId']) ? [] : [$fields['categoryId']]),
            'expansionId' => $row['expansion_id'], 'classIds' => $fields['classIds'] ?? [],
            'professionIds' => $fields['professionIds'] ?? [], 'roles' => $fields['roles'] ?? [],
            'summary' => $fields['summary'] ?? null, 'description' => $fields['description'] ?? null,
            'tags' => $fields['tags'] ?? [], 'originalAuthor' => $fields['originalAuthor'] ?? null,
            'sourceUrl' => $fields['sourceUrl'] ?? null, 'icon' => $icon, 'screenshots' => $screens,
            'rulesAccepted' => $fields['rulesAccepted'] ?? false,
            'testedConfirmed' => $fields['testedConfirmed'] ?? false,
            'createdAt' => $this->date($row['created_at']), 'updatedAt' => $this->date($row['updated_at']),
            'submittedAt' => $this->date($row['submitted_at']), 'expiresAt' => $expiry,
            'decision' => $fields['decision'] ?? null, 'estimatedReviewHours' => $row['status'] === 'pending'
                ? (new CatalogService($this->app))->site_stats()['averageReviewHours'] : null,
        ];
    }

    // =========================================================================
    // DRAFT FIELDS AND ADDON OWNERSHIP
    // =========================================================================

    private function validate_fields(array $body, array $before = []): array {
        $fields = $before; $limits = [
            'name' => [3, 60], 'summary' => [1, 120], 'description' => [0, 20000], 'originalAuthor' => [1, 60],
            'sourceUrl' => [1, 300], 'changelog' => [0, 4000], 'title' => [0, 200], 'clientPatch' => [1, 30],
        ];
        foreach ($limits as $key => [$min, $max]) {
            if (array_key_exists($key, $body)) {
                $value = $body[$key];
                if ($value !== null) V::text($value, $key, $min, $max);
                if ($key === 'sourceUrl' && $value !== null && (
                    !filter_var($value, FILTER_VALIDATE_URL)
                    || !in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)
                )) V::fail($key);
                $fields[$key] = $value;
            }
        }
        $groups = [
            'categoryId' => 'category', 'classIds' => 'classes', 'professionIds' => 'professions', 'roles' => 'role',
        ];
        foreach ($groups as $key => $group) {
            if (array_key_exists($key, $body)) {
                if ($key === 'categoryId') $fields[$key] = $body[$key] === null ? null
                    : V::choice($body[$key], $key, CatalogService::GROUPS[$group]);
                else {
                    $values = V::items($body[$key], $key, count(CatalogService::GROUPS[$group]));
                    foreach ($values as $value) V::choice($value, $key, CatalogService::GROUPS[$group]);
                    $fields[$key] = array_values(array_unique($values));
                }
            }
        }
        // The first selected category also supplies the primary category field.
        if (array_key_exists('categoryIds', $body)) {
            $values = V::items($body['categoryIds'], 'categoryIds', count(CatalogService::GROUPS['category']));
            foreach ($values as $value) V::choice($value, 'categoryIds', CatalogService::GROUPS['category']);
            $fields['categoryIds'] = array_values(array_unique($values));
            $fields['categoryId'] = $fields['categoryIds'][0] ?? null;
        } elseif (array_key_exists('categoryId', $body)) {
            $fields['categoryIds'] = $fields['categoryId'] === null ? [] : [$fields['categoryId']];
        }
        if (array_key_exists('tags', $body)) {
            $values = V::items($body['tags'], 'tags', 10);
            foreach ($values as &$value) $value = mb_strtolower(V::text($value, 'tags', 2, 24));
            unset($value); $fields['tags'] = array_values(array_unique($values));
        }
        foreach (['rulesAccepted', 'testedConfirmed'] as $key) {
            if (array_key_exists($key, $body)) $fields[$key] = V::boolean($body[$key], $key);
        }
        return $fields;
    }

    private function addon(array $body, ?array $existing = null): ?array {
        $id = null; $slug = $body['addonSlug'] ?? null;
        if (
            $id === null && $slug === null && !array_key_exists('addonSlug', $body) && !empty($existing['addon_id'])
        ) $id = $existing['addon_id'];
        if ($id === null && $slug === null) return null;
        $row = $id !== null ? $this->row(
            'SELECT * FROM addons WHERE id=:id AND withdrawn_at IS NULL', ['id' => V::integer($id, 'addonId')]
        ) : (new CatalogService($this->app))->find(V::text($slug, 'addonSlug', 1, 200));
        if (
            (int)$row['owner_id'] !== $this->user_id() && !$this->staff()
        ) throw new ApiException(403, 'NOT_ADDON_OWNER');
        return $row;
    }

    // =========================================================================
    // OWNED UPLOAD LINKS
    // =========================================================================

    /**
     * Replace only the supplied slots, validating ownership, kind, uniqueness,
     * and expiry.
     */
    private function link_files(int $id, array $body): void {
        $slots = [
            'uploadId' => ['zip', 'zip'], 'iconMediaId' => ['icon', 'icon'],
            'screenshotMediaIds' => ['screenshot', 'screenshot'],
        ];
        foreach ($slots as $key => [$slot, $kind]) {
            if (!array_key_exists($key, $body)) continue;
            $values = $slot === 'screenshot' ? V::items($body[$key], $key, 4)
                : ($body[$key] === null ? [] : [$body[$key]]);
            if (count($values) !== count(array_unique($values, SORT_REGULAR))) V::fail($key);
            $assets = [];
            foreach ($values as $value) {
                if (!is_string($value) && !is_int($value)) V::fail($key);
                $query = 'SELECT * FROM assets WHERE ' . ($slot === 'zip' ? 'external_id::text' : 'id::text');
                $query .= '=:id AND owner_id=:owner AND kind=:kind FOR UPDATE';
                $asset = $this->db()->one($query, [
                    'id' => (string)$value, 'owner' => $this->user_id(), 'kind' => $kind,
                ]);
                if (!$asset || $asset['status'] === 'removed') V::fail($key);
                if (
                    $asset['expires_at'] !== null && strtotime($asset['expires_at']) <= time()
                ) throw new ApiException(410, $slot === 'zip' ? 'UPLOAD_EXPIRED' : 'MEDIA_EXPIRED');
                $assets[] = $asset;
            }
            // Detached assets receive a grace period only when no other links
            // remain.
            $query = 'DELETE FROM asset_links WHERE submission_id=:id AND slot=:slot RETURNING asset_id';
            $old = $this->db()->all($query, ['id' => $id, 'slot' => $slot]);
            foreach ($old as $link) $this->db()->query(
                "UPDATE assets SET expires_at=now()+interval '24 hours' WHERE id=:id "
                    . 'AND NOT EXISTS(SELECT 1 FROM asset_links WHERE asset_id=:id)', ['id' => $link['asset_id']]
            );
            foreach ($assets as $position => $asset) $this->db()->query(
                'INSERT INTO asset_links(asset_id,submission_id,slot,position) '
                    . 'VALUES (:asset,:submission,:slot,:position)',
                [
                    'asset' => $asset['id'], 'submission' => $id, 'slot' => $slot,
                    'position' => $slot === 'screenshot' ? $position + 1 : 0,
                ]
            );
        }
        (new FileService($this->app))->refresh_expiry($id);
    }

    // =========================================================================
    // DRAFT PERSISTENCE AND REVISION CHECKS
    // =========================================================================

    /**
     * Merge validated fields, persist links, and reconcile both old and new
     * community requests.
     */
    private function save_row(?array $row): array {
        $body = $this->request->body; $fields = $this->validate_fields($body, $row ? $this->json($row['fields']) : []);
        $addon = $this->addon($body, $row);
        if ($row && (($row['kind'] === 'version') !== ($addon !== null))) throw new ApiException(409, 'INVALID_STATE');
        $version = array_key_exists('version', $body) ? $body['version'] : ($row['version_label'] ?? null);
        if ($version !== null) {
            V::text($version, 'version', 1, 20);
            if (!preg_match('/^[0-9A-Za-z._-]+$/D', $version)) V::fail('version');
        }
        $exp = array_key_exists('expansionId', $body) ? $body['expansionId'] : ($row['expansion_id'] ?? null);
        if ($exp !== null) $this->expansion($exp);
        $request = array_key_exists('requestId', $body) ? $body['requestId'] : ($row['request_id'] ?? null);
        if ($request !== null) {
            $request = V::integer($request, 'requestId');
            $target = $this->row('SELECT * FROM requests WHERE id=:id FOR UPDATE', ['id' => $request]);
            if (
                !in_array($target['state'], ['open', 'volunteered'], true)
            ) throw new ApiException(409, 'INVALID_STATE');
            if ($exp !== null && $target['expansion_id'] !== $exp) V::fail('expansionId');
        }
        $params = [
            'addon' => $addon['id'] ?? null, 'request' => $request, 'fields' => $this->encode((object)$fields),
            'expansion' => $exp, 'version' => $version,
        ];
        if ($row) {
            $query = 'UPDATE submissions SET addon_id=:addon,request_id=:request,fields=CAST(:fields AS jsonb),';
            $query .= 'expansion_id=:expansion,version_label=:version WHERE id=:id RETURNING *';
            $saved = $this->row($query, $params + ['id' => $row['id']]);
        } else {
            $query = 'INSERT INTO submissions(owner_id,kind,addon_id,request_id,fields,expansion_id,version_label) ';
            $query .= 'VALUES (:owner,:kind,:addon,:request,CAST(:fields AS jsonb),:expansion,:version) RETURNING *';
            $saved = $this->row($query, $params + [
                'owner' => $this->user_id(), 'kind' => $addon ? 'version' : 'addon',
            ]);
        }
        $this->link_files((int)$saved['id'], $body); $requests = new RequestService($this->app);
        if (
            $row && $row['request_id'] !== null && $row['request_id'] !== $request
        ) $requests->reconcile((int)$row['request_id']);
        if ($request !== null) $requests->reconcile($request);
        return $saved;
    }

    public function create(): array {
        return $this->db()->transaction(fn() => ['submission' => $this->present($this->save_row(null))]);
    }

    public function show(): array {
        $row = $this->owned(); $result = ['submission' => $this->present($row)];
        return $result;
    }

    /**
     * Reject a supplied revision when another edit has already changed the
     * submission.
     */
    private function revision(array $row): void {
        if (
            isset($this->request->body['revision'])
            && V::integer($this->request->body['revision'], 'revision') !== (int)$row['revision']
        ) throw new ApiException(409, 'REVISION_MISMATCH');
    }

    public function update(): array {
        return $this->db()->transaction(function () {
            $row = $this->owned(true);
            if ($row['status'] !== 'draft') throw new ApiException(409, 'INVALID_STATE');
            $this->revision($row);
            return ['submission' => $this->present($this->save_row($row))];
        });
    }

    public function listing(): Response {
        [$limit, $offset] = $this->pagination(10, 50); $params = ['owner' => $this->user_id()];
        $where = "owner_id=:owner AND (status<>'draft' OR updated_at>now()-interval '30 days')";
        if (isset($this->request->query['status'])) {
            $params['status'] = V::choice($this->request->query['status'], 'status', [
                'draft', 'pending', 'approved', 'rejected',
            ]);
            $where .= ' AND status=:status';
        }
        $query = 'SELECT * FROM submissions WHERE ' . $where;
        $query .= ' ORDER BY updated_at DESC,id DESC LIMIT :limit OFFSET :offset';
        return $this->page(
            array_map(fn($r) => $this->present($r), $this->db()->all(
                $query, $params + ['limit' => $limit, 'offset' => $offset]
            )),
            (int)$this->db()->value('SELECT count(*) FROM submissions WHERE ' . $where, $params), $offset, $limit
        );
    }

    // =========================================================================
    // FINAL VALIDATION AND SUBMISSION
    // =========================================================================

    /**
     * Require accepted rules, complete metadata, usable files, and matching
     * addon ownership.
     */
    private function ready(array $row): void {
        $fields = $this->json($row['fields']);
        foreach (['rulesAccepted', 'testedConfirmed'] as $key) if (($fields[$key] ?? false) !== true) V::fail($key);
        foreach (['expansion_id', 'version_label'] as $key) if ($row[$key] === null) V::fail($key);
        if ($row['kind'] === 'addon') foreach (['name', 'categoryId', 'summary'] as $key) {
            if (empty($fields[$key])) V::fail($key);
        }
        $zip = null;
        foreach ((new FileService($this->app))->linked((int)$row['id']) as $asset) {
            if (!in_array($asset['status'], ['clean', 'warning'], true)) V::fail('uploadId');
            if (
                $asset['expires_at'] !== null && strtotime($asset['expires_at']) <= time()
            ) throw new ApiException(410, 'UPLOAD_EXPIRED');
            if ($asset['slot'] === 'zip') $zip = $asset;
        }
        if (!$zip) V::fail('uploadId');
        // A detected client expansion must agree with the submitted expansion.
        $meta = $this->json($zip['metadata']);
        if (
            ($meta['detectedExpansionId'] ?? $row['expansion_id']) !== $row['expansion_id']
        ) V::fail('expansionId', 'INTERFACE_MISMATCH');
        if ($row['addon_id']) {
            $addon = $this->row('SELECT * FROM addons WHERE id=:id AND withdrawn_at IS NULL FOR SHARE', [
                'id' => $row['addon_id'],
            ]);
            if (
                !$this->staff() && (int)$addon['owner_id'] !== $this->user_id()
            ) throw new ApiException(403, 'NOT_ADDON_OWNER');
        }
    }

    /**
     * Reserve the normalized addon name before moving the draft into the review
     * queue.
     */
    public function submit(): Response {
        return $this->db()->transaction(function () {
            $row = $this->owned(true);
            if ($row['status'] !== 'draft') throw new ApiException(409, 'INVALID_STATE');
            $this->revision($row); $this->ready($row); $fields = $this->json($row['fields']);
            if ($row['kind'] === 'addon') {
                $key = $this->db()->value('SELECT normalize_name(:name)', ['name' => $fields['name']]);
                if ($key === '') V::fail('name');
                $this->db()->query("INSERT INTO name_claims(key,submission_id,kinds) VALUES (:key,:id,ARRAY['name'])", [
                    'key' => $key, 'id' => $row['id'],
                ]);
            }
            $query = "UPDATE submissions SET status='pending',submitted_at=clock_timestamp() WHERE id=:id RETURNING *";
            $row = $this->row($query, ['id' => $row['id']]);
            if ($row['request_id']) (new RequestService($this->app))->transition((int)$row['request_id'], 'inQueue');
            (new FileService($this->app))->refresh_expiry((int)$row['id']);
            $this->audit('upload', 'submission', $row['id'], ['targetLabel' => $fields['name'] ?? 'Version']);
            return Response::ok($this->request, ['submission' => $this->present($row)]);
        });
    }

    // =========================================================================
    // MODERATION QUEUE AND FILE INSPECTION
    // =========================================================================

    public function queue_item(array $row): array {
        $fields = $this->json($row['fields']);
        $query = 'SELECT f.* FROM asset_links l JOIN assets f ON f.id=l.asset_id ';
        $query .= "WHERE l.submission_id=:id AND l.slot='icon'";
        $icon = $this->db()->one($query, ['id' => $row['id']]);
        $query = 'SELECT f.* FROM asset_links l JOIN assets f ON f.id=l.asset_id ';
        $query .= "WHERE l.submission_id=:id AND l.slot='zip'";
        $zip = $this->db()->one($query, ['id' => $row['id']]);
        $meta = $zip ? $this->json($zip['metadata']) : []; $author = $this->user_ref($row['owner_id']);
        $query = "SELECT count(*) FROM submissions WHERE owner_id=:id AND status='approved'";
        $author['approvedUploads'] = (int)$this->db()->value($query, ['id' => $row['owner_id']]);
        return [
            'id' => $this->external_id($row['id']), 'kind' => $row['kind'],
            'name' => $fields['name'] ?? ($row['addon_id'] ? $this->db()->value(
                'SELECT name FROM addons WHERE id=:id', ['id' => $row['addon_id']]
            ) : ''),
            'version' => $row['version_label'], 'versionLabel' => $row['version_label'],
            'expansionId' => $row['expansion_id'],
            'iconUrl' => $icon ? (new FileService($this->app))->url($icon) : null,
            'author' => $author, 'size' => (int)($zip['size_bytes'] ?? 0), 'folders' => count($meta['folders'] ?? []),
            'note' => $fields['description'] ?? null, 'flag' => $meta['warnings'][0] ?? null,
            'submittedAt' => $this->date($row['submitted_at']),
        ];
    }

    public function queue(): Response {
        [$limit, $offset] = $this->pagination(20, 50);
        $where = ["s.status='pending'"]; $params = []; $q = $this->request->query;
        if (isset($q['q'])) {
            $where[] = "(coalesce(s.fields->>'name',a.name,'') ILIKE :q OR u.username ILIKE :q)";
            $params['q'] = '%' . V::text($q['q'], 'q', 1, 100) . '%';
        }
        if (isset($q['kind'])) {
            $where[] = 's.kind=:kind'; $params['kind'] = V::choice($q['kind'], 'kind', ['addon', 'version']);
        }
        if (isset($q['flagged'])) {
            $flag = V::choice((string)$q['flagged'], 'flagged', ['flagged', 'clean', 'true', 'false', '1', '0']);
            $where[] = in_array($flag, ['flagged', 'true', '1'], true) ? "f.status<>'clean'" : "f.status='clean'";
        }
        $sort = V::choice($q['sort'] ?? 'oldest', 'sort', ['oldest', 'newest', 'flagged', 'size']);
        $order = match($sort) {
            'newest' => 's.submitted_at DESC', 'flagged' => "(f.status<>'clean') DESC,s.submitted_at",
            'size' => 'f.size_bytes DESC', default => 's.submitted_at',
        };
        $base = ' FROM submissions s JOIN accounts u ON u.id=s.owner_id LEFT JOIN addons a ON a.id=s.addon_id ';
        $base .= "JOIN asset_links l ON l.submission_id=s.id AND l.slot='zip' ";
        $base .= 'JOIN assets f ON f.id=l.asset_id WHERE ' . implode(' AND ', $where);
        $query = "SELECT count(*)::int AS pending,count(*) FILTER(WHERE f.status<>'clean')::int AS flagged,";
        $query .= 'ceil(max(extract(epoch FROM now()-s.submitted_at))/3600)::int AS "oldestWaitHours" ';
        $query .= "FROM submissions s JOIN asset_links l ON l.submission_id=s.id AND l.slot='zip' ";
        $query .= "JOIN assets f ON f.id=l.asset_id WHERE s.status='pending'";
        $counts = $this->row($query);
        $days = (int)rtrim(V::choice($q['range'] ?? '7d', 'range', ['7d', '30d', '90d']), 'd');
        $query = 'SELECT count(*)::int AS count,round(avg(extract(epoch FROM decided_at-submitted_at)/3600))::int ';
        $query .= 'AS "averageHours" FROM submissions WHERE decided_at>now()-make_interval(days=>:days)';
        $decided = $this->row($query, ['days' => $days]);
        $query = 'SELECT s.*' . $base . ' ORDER BY ' . $order . ',s.id LIMIT :limit OFFSET :offset';
        return $this->page(
            array_map(fn($r) => $this->queue_item($r), $this->db()->all(
                $query, $params + ['limit' => $limit, 'offset' => $offset]
            )),
            (int)$this->db()->value('SELECT count(*)' . $base, $params), $offset, $limit,
            ['counts' => $counts, 'decided' => $decided, 'summary' => $counts + [
                'decidedInRange' => $decided['count'], 'averageDecisionHours' => $decided['averageHours'],
            ]]
        );
    }

    public function inspect(): array {
        $query = "SELECT * FROM submissions WHERE id=:id AND status<>'draft'";
        $row = $this->row($query, ['id' => $this->submission_id()]);
        return [
            'submission' => $this->present($row),
            'author' => (new AdminService($this->app))->account((int)$row['owner_id']),
            'addon' => $row['addon_id'] ? (new CatalogService($this->app))->summary(
                $this->row('SELECT * FROM addons WHERE id=:id', ['id' => $row['addon_id']])
            ) : null,
        ];
    }

    /**
     * Stream the ZIP of a submitted item through the staff-authorized route.
     */
    public function file(): Response {
        $id = $this->submission_id(); $query = 'SELECT f.* FROM submissions s ';
        $query .= "JOIN asset_links l ON l.submission_id=s.id AND l.slot='zip' ";
        $query .= "JOIN assets f ON f.id=l.asset_id WHERE s.id=:id AND s.status<>'draft'";
        $asset = $this->row($query, ['id' => $id]);
        $response = Response::stream((new FileService($this->app))->path($asset), 'submission-' . $id . '.zip');
        $response->headers['X-Submission-Id'] = (string)$id; return $response;
    }

    // =========================================================================
    // MODERATION DECISION AND PUBLICATION
    // =========================================================================

    public function decision(): array {
        $decision = V::choice($this->request->input('decision'), 'decision', ['approve', 'reject']);
        $rule = $this->request->input('ruleId'); $reason = $this->request->input('reason');
        if ($reason !== null) V::text($reason, 'reason', 0, 4000);
        if ($decision === 'reject') {
            V::choice($rule, 'ruleId', ['clean', 'free', 'credit', 'version', 'duplicates', 'honest', 'privacy']);
            V::text($reason, 'reason', 1, 4000);
        }
        return $this->db()->transaction(function () use ($decision, $rule, $reason) {
            $row = $this->row('SELECT * FROM submissions WHERE id=:id FOR UPDATE', ['id' => $this->submission_id()]);
            if ($row['status'] !== 'pending') throw new ApiException(409, 'INVALID_STATE');
            $fields = $this->json($row['fields']); $addon_id = $row['addon_id']; $slug = null;
            if ($decision === 'approve') {
                $this->ready($row);
                if ($row['kind'] === 'addon') {
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $fields['name']), '-'));
                    if ($slug === '') $slug = 'addon-' . $row['id'];
                    $key = $this->db()->value('SELECT normalize_name(:slug)', ['slug' => $slug]);
                    // Check the normalized slug claim before creating the new
                    // published addon.
                    $claim = $this->db()->one('SELECT * FROM name_claims WHERE key=:key FOR UPDATE', ['key' => $key]);
                    if (
                        $claim && (int)$claim['submission_id'] !== (int)$row['id']
                    ) throw new ApiException(409, 'NAME_TAKEN');
                    $details = $fields;
                    unset(
                        $details['rulesAccepted'], $details['testedConfirmed'], $details['name'], $details['categoryId']
                    );
                    $query = 'INSERT INTO addons(slug,owner_id,name,category_id,details) ';
                    $query .= 'VALUES (:slug,:owner,:name,:category,CAST(:details AS jsonb)) RETURNING id';
                    $addon_id = $this->db()->value($query, [
                        'slug' => $slug, 'owner' => $row['owner_id'], 'name' => $fields['name'],
                        'category' => $fields['categoryId'], 'details' => $this->encode($details),
                    ]);
                    // Transfer pending name reservations to the newly created
                    // addon.
                    $query = 'UPDATE name_claims SET addon_id=:addon,submission_id=NULL WHERE submission_id=:id';
                    $this->db()->query($query, ['addon' => $addon_id, 'id' => $row['id']]);
                    $claims = new NameClaim($this->db()); $reserved = $claims->get($key);
                    if ($reserved) {
                        if ($reserved->addon_id !== (int)$addon_id) throw new ApiException(409, 'NAME_TAKEN');
                        $reserved->kinds = array_values(array_unique([...$reserved->kinds, 'slug'])); $reserved->save();
                    } else $claims->create(['key' => $key, 'addon_id' => (int)$addon_id, 'kinds' => ['slug']]);
                } else $slug = $this->db()->value('SELECT slug FROM addons WHERE id=:id', ['id' => $addon_id]);
                foreach ((new FileService($this->app))->linked((int)$row['id']) as $asset) {
                    // Promote the approved submission images into the addon
                    // display slots.
                    if (in_array($asset['slot'], ['icon', 'screenshot'], true)) {
                        $query = 'DELETE FROM asset_links WHERE addon_id=:addon AND slot=:slot AND position=:position';
                        $this->db()->query($query, [
                            'addon' => $addon_id, 'slot' => $asset['slot'], 'position' => $asset['position'],
                        ]);
                        $query = 'INSERT INTO asset_links(asset_id,addon_id,slot,position) ';
                        $query .= 'VALUES (:asset,:addon,:slot,:position)';
                        $this->db()->query($query, [
                            'asset' => $asset['id'], 'addon' => $addon_id,
                            'slot' => $asset['slot'], 'position' => $asset['position'],
                        ]);
                    }
                }
            } else $this->db()->query('DELETE FROM name_claims WHERE submission_id=:id', ['id' => $row['id']]);
            // Store the decision together with reviewer metadata before
            // updating linked request state.
            $status = $decision === 'approve' ? 'approved' : 'rejected'; $fields['decision'] = [
                'status' => $status, 'ruleId' => $rule, 'reason' => $reason,
                'decidedAt' => gmdate('c'), 'addonSlug' => $slug,
            ];
            $query = 'UPDATE submissions SET status=:status,addon_id=:addon,fields=CAST(:fields AS jsonb),';
            $query .= 'decided_at=clock_timestamp(),reviewer_id=:reviewer WHERE id=:id RETURNING *';
            $row = $this->row($query, [
                'status' => $status, 'addon' => $addon_id, 'fields' => $this->encode($fields),
                'reviewer' => $this->user_id(), 'id' => $row['id'],
            ]);
            if ($row['request_id']) {
                $requests = new RequestService($this->app);
                if ($decision === 'approve') $requests->transition(
                    (int)$row['request_id'], 'fulfilled', (int)$addon_id
                );
                else $requests->reconcile((int)$row['request_id']);
            }
            $event = $this->audit($decision, 'submission', $row['id'], [
                'targetLabel' => $fields['name'] ?? $slug ?? 'Version', 'addonSlug' => $slug,
                'ownerId' => (int)$row['owner_id'], 'detail' => $reason,
            ]);
            (new NotificationService($this->app))->account(
                (int)$row['owner_id'], 'Submission ' . $status,
                $reason ?? 'Your submission is published.', 'decision:' . $event
            );
            (new FileService($this->app))->refresh_expiry((int)$row['id']);
            return [
                'submission' => $this->present($row), 'addon' => $decision === 'approve'
                    ? (new CatalogService($this->app))->summary(
                        $this->row('SELECT * FROM addons WHERE id=:id', ['id' => $addon_id])
                    ) : null,
            ];
        });
    }
}
