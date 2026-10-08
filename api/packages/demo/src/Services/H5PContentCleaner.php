<?php

namespace Ulams\Demo\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\H5P\Exceptions\H5PServiceException;
use Ulams\H5P\Models\H5PContent;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;

/**
 * Deletes the tenant's H5P content in the H5P service (api/h5p) before a demo reset. The
 * service keeps it in schema `h5p` of the tenant database and in the tenant bucket;
 * `migrate:fresh` only drops the `public` schema, so without this every reset would add
 * another copy of the demo content.
 *
 * Everything goes over HTTP through the h5p package's client, which sends the tenant host
 * (X-Forwarded-Host) and the internal token: the service only touches that tenant.
 */
class H5PContentCleaner
{
    public function __construct(private Container $app)
    {
    }

    public function available(): bool
    {
        return interface_exists(H5PServiceClientContract::class)
            && $this->app->bound(H5PServiceClientContract::class);
    }

    /**
     * Content ids the tenant database knows: every row of `h5p.contents` and every content
     * an H5P topic points at (`topic_h5ps.value`).
     *
     * @return list<int>
     */
    public function knownContentIds(): array
    {
        $ids = [];
        if (class_exists(H5PContent::class) && Schema::hasTable((new H5PContent())->getTable())) {
            $ids = [...$ids, ...H5PContent::query()->pluck('id')->all()];
        }
        if (Schema::hasTable('topic_h5ps')) {
            $ids = [...$ids, ...DB::table('topic_h5ps')->whereNotNull('value')->pluck('value')->all()];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(fn ($id) => is_numeric($id) ? (int) $id : 0, $ids),
            fn (int $id) => $id > 0
        )));
        sort($ids);

        return $ids;
    }

    /**
     * Deletes every known content (`DELETE /h5p/contents/{id}`), then the files the service
     * still holds for contents without a row (`POST /h5p/contents/orphans/delete`).
     *
     * @return array{deleted: int, missing: int, failed: array<int, string>, orphan_contents: int, orphan_files: int}
     *
     * @throws H5PServiceException when the service cannot be reached
     */
    public function clean(): array
    {
        $client = $this->app->make(H5PServiceClientContract::class);
        $result = ['deleted' => 0, 'missing' => 0, 'failed' => [], 'orphan_contents' => 0, 'orphan_files' => 0];

        foreach ($this->knownContentIds() as $id) {
            try {
                $client->delete($id) ? $result['deleted']++ : $result['missing']++;
            } catch (H5PServiceException $exception) {
                if ($exception->getStatus() === null) {
                    // unreachable: the remaining ids would fail the same way
                    throw $exception;
                }
                $result['failed'][$id] = $exception->getMessage();
            }
        }

        $orphans = $client->deleteOrphans();
        $result['orphan_contents'] = count($orphans['contentIds'] ?? []);
        $result['orphan_files'] = (int) ($orphans['files'] ?? 0);

        return $result;
    }
}
