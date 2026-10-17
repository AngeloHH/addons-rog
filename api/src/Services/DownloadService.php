<?php

/**
 * Addons ROG - Addon downloads and packs
 *
 * - Count published version downloads before issuing short-lived signed redirects.
 * - Build cached addon packs with validated members and collision checks.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Http\{ApiException, Validator as V, Response};
use RuntimeException;
use Throwable;
use ZipArchive;

class DownloadService extends Service {

    // =========================================================================
    // PUBLISHED VERSIONS AND CLIENT COUNTING
    // =========================================================================

    private function version_row(int $id): array {
        $query = 'SELECT v.*,f.storage_key,f.filename,f.size_bytes,a.slug,a.name FROM published_versions v ';
        $query .= 'JOIN assets f ON f.id=v.asset_id JOIN addons a ON a.id=v.addon_id WHERE v.id=:id';
        return $this->row($query, ['id' => $id]);
    }

    /**
     * Use an HMAC client key for the database download-counting window.
     */
    private function count(int $id, ?string $pack = null): void {
        $ip = $this->request->ip($this->app->config['trusted_proxies']);
        $key = hash_hmac('sha256', $ip, $this->app->config['secret']);
        $this->db()->record_download($id, $this->user_id() ?: null, $key, pack: $pack);
    }

    // =========================================================================
    // COUNTED DOWNLOAD REDIRECTS
    // =========================================================================

    public function version(): Response {
        $this->rate('download', 120, 60); $row = $this->version_row($this->id('versionId'));
        $path = (new FileService($this->app))->path($row);
        $name = $row['slug'] . '-' . $row['version_label'] . '-' . $row['expansion_id'] . '.zip';
        $response = Response::stream($path, $name); $this->count((int)$row['id']);
        // The file URL is signed for five minutes and does not count a second
        // download.
        $expires = time() + 300;
        $signature = hash_hmac('sha256', $row['id'] . ':' . $expires, $this->app->config['secret']);
        return new Response(null, 302, [
            'Location' => $this->app->config['origin'] . '/api/download-files/' . $row['id']
                . '?expires=' . $expires . '&signature=' . $signature,
            'Content-Disposition' => $response->headers['Content-Disposition'],
        ]);
    }

    /**
     * Validate the signature and time window, then recheck that the version is
     * still published.
     */
    public function signed(): Response {
        $id = $this->id('versionId'); $expires = V::integer($this->request->query['expires'] ?? null, 'expires');
        $signature = $this->request->query['signature'] ?? '';
        if (
            $expires < time() || $expires > time() + 300 || !is_string($signature)
            || !hash_equals(hash_hmac('sha256', $id . ':' . $expires, $this->app->config['secret']), $signature)
        ) throw new ApiException(403, 'FORBIDDEN');

        $row = $this->version_row($id);
        return Response::stream(
            (new FileService($this->app))->path($row),
            $row['slug'] . '-' . $row['version_label'] . '-' . $row['expansion_id'] . '.zip'
        );
    }

    // =========================================================================
    // PACK MEMBERS AND CACHED ARCHIVES
    // =========================================================================

    /**
     * Require the latest published version of every configured member for the
     * selected expansion.
     */
    private function members(array $definition, string $expansion): array {
        $slugs = $definition['membersByExpansion'][$expansion] ?? [];
        if (!$slugs) throw new ApiException(404, 'NOT_FOUND');
        $members = [];
        foreach ($slugs as $slug) {
            $query = 'SELECT v.id FROM latest_versions v JOIN addons a ON a.id=v.addon_id ';
            $query .= 'WHERE a.slug=:slug AND v.expansion_id=:expansion';
            $v = $this->db()->one($query, ['slug' => $slug, 'expansion' => $expansion]);
            if (!$v) throw new ApiException(409, 'PACK_INCOMPLETE');
            $members[] = $this->version_row((int)$v['id']);
        }
        return $members;
    }

    private function archive(string $pack, string $expansion, array $members): string {
        // The pack cache key changes whenever its selected version IDs change.
        $key = hash('sha256', $pack . ':' . $expansion . ':' . implode(',', array_column($members, 'id')));
        $dir = $this->app->config['storage'] . '/packs'; $error = 'Cannot create pack directory';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException($error);

        $path = $dir . '/' . $key . '.zip'; $lock = fopen($dir . '/' . $key . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock pack');
        $tmp = null;
        try {
            // Reuse an archive only after acquiring the per-pack build lock.
            if (is_file($path)) return $path;
            $tmp = tempnam($dir, 'build-'); $out = new ZipArchive();
            $error = 'Cannot create pack';
            if ($tmp === false || $out->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException($error);
            $seen = []; $total = 0;
            try {
                foreach ($members as $member) {
                    $source = (new FileService($this->app))->path($member); (new ZipScanner())->scan($source);
                    $zip = new ZipArchive();
                    if (
                        $zip->open($source, ZipArchive::RDONLY) !== true
                    ) throw new ApiException(409, 'PACK_INCOMPLETE');
                    try {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $stat = $zip->statIndex($i); $name = $stat['name'];
                            if (str_ends_with($name, '/')) continue;
                            // Reject colliding member paths rather than
                            // overwriting files from another addon.
                            if (isset($seen[strtolower($name)])) throw new ApiException(409, 'PACK_PATH_CONFLICT');
                            $seen[strtolower($name)] = true; $total += $stat['size'];
                            if ($total > 536870912) throw new ApiException(413, 'FILE_TOO_LARGE');
                            $contents = $zip->getFromIndex($i);
                            if (
                                $contents === false || !$out->addFromString($name, $contents)
                            ) throw new ApiException(409, 'PACK_INCOMPLETE');
                        }
                    } finally { $zip->close(); }
                }
            } catch (Throwable $error) { $out->close(); throw $error; }
            // Publish the completed temporary archive only after ZIP
            // finalization succeeds.
            if (!$out->close() || !rename($tmp, $path)) throw new RuntimeException('Cannot finish pack');
            $tmp = null; return $path;
        } finally {
            if ($tmp !== null && is_file($tmp)) unlink($tmp);
            flock($lock, LOCK_UN); fclose($lock);
        }
    }

    // =========================================================================
    // PACK DOWNLOADS AND AVAILABILITY
    // =========================================================================

    public function pack(): Response {
        $this->rate('pack', 10, 60); $id = V::text($this->request->param('packId'), 'packId', 1, 100);
        $exp = $this->expansion($this->request->query['expansion'] ?? $this->request->query['expansionId'] ?? null);
        $definition = $this->config('packDefinitions')[$id] ?? null;
        if (!$definition) throw new ApiException(404, 'NOT_FOUND');
        $members = $this->members($definition, $exp); $path = $this->archive($id, $exp, $members);
        $this->db()->transaction(function () use ($members, $id) {
            foreach ($members as $member) {
                $this->version_row((int)$member['id']); $this->count((int)$member['id'], $id);
            }
        });
        $response = Response::stream($path, 'rog-' . $id . '-kit-' . $exp . '.zip');
        $response->headers += ['X-Pack-Id' => $id, 'X-Expansion-Id' => $exp]; return $response;
    }

    /**
     * Describe available expansion variants, skipping definitions whose members
     * cannot be built.
     */
    public function packs(): array {
        $packs = [];
        foreach ($this->config('packDefinitions') as $id => $definition) {
            $availability = [];
            foreach ($definition['membersByExpansion'] ?? [] as $exp => $slugs) {
                try { $members = $this->members($definition, $exp); $path = $this->archive($id, $exp, $members); }
                catch (ApiException) { continue; }
                $availability[] = [
                    'expansionId' => $exp,
                    'addons' => array_map(fn($m) => ['slug' => $m['slug'], 'name' => $m['name']], $members),
                    'size' => filesize($path),
                    'downloadUrl' => $this->app->config['origin'] . '/api/packs/' . $id . '/download?expansion=' . $exp,
                ];
            }
            $packs[] = [
                'id' => $id, 'name' => $definition['name'] ?? $id,
                'description' => $definition['description'] ?? '', 'availability' => $availability,
                'imageUrl' => $definition['imageUrl'] ?? null, 'addonCount' => count($availability[0]['addons'] ?? []),
                'sizeBytes' => $availability[0]['size'] ?? 0,
                'expansionIds' => array_column($availability, 'expansionId'),
            ];
        }
        return $packs;
    }
}
