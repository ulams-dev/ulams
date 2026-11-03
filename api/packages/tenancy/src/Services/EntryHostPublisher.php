<?php

namespace Ulams\Tenancy\Services;

use Ulams\Tenancy\Models\EntryHostPublication;
use Ulams\Tenancy\Models\EntryPoint;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\EntryHostPublisherContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Support\TenantNaming;

/**
 * Publishes the active entry hosts of an academy into its own database by running `ulams:entry-points:publish` in a
 * process of that academy (the way the platform already runs `migrate` for it). The hash of what was published is kept,
 * so an unchanged list costs nothing and a failed publication is found again by {@see reconcile()}.
 */
class EntryHostPublisher implements EntryHostPublisherContract
{
    public function __construct(private readonly TenantCommandRunnerContract $runner)
    {
    }

    public function publish(Tenant $tenant, bool $force = false): void
    {
        $hosts = $this->hostsOf($tenant);
        $hash = self::hashOf($hosts);
        $known = EntryHostPublication::query()->find($tenant->id)?->hash ?? self::hashOf([]);
        if (!$force && $known === $hash) {
            return;
        }

        $this->runner->run($tenant->api_host, ['ulams:entry-points:publish', '--payload=' . base64_encode((string) json_encode($hosts))]);
        EntryHostPublication::query()->updateOrCreate(['tenant_id' => $tenant->id], ['hash' => $hash, 'published_at' => now()]);
    }

    /**
     * Publishes to every academy whose copy is out of date: the ones with entry points, and the ones that had.
     *
     * @return array{published: list<string>, failed: array<string, string>}
     */
    public function reconcile(?array $slugs = null): array
    {
        $ids = EntryPoint::query()->distinct()->pluck('tenant_id')->merge(EntryHostPublication::query()->pluck('tenant_id'))->unique();
        $result = ['published' => [], 'failed' => []];
        foreach (Tenant::query()->whereIn('id', $ids)->where('status', Tenant::STATUS_ACTIVE)->orderBy('slug')->get() as $tenant) {
            if ($slugs !== null && !in_array($tenant->slug, $slugs, true)) {
                continue;
            }
            $known = EntryHostPublication::query()->find($tenant->id)?->hash ?? self::hashOf([]);
            if ($known === self::hashOf($this->hostsOf($tenant))) {
                continue;
            }
            try {
                $this->publish($tenant);
                $result['published'][] = $tenant->slug;
            } catch (\Throwable $e) {
                $result['failed'][$tenant->slug] = $e->getMessage();
            }
        }

        return $result;
    }

    /** @return list<array{host: string, origin: string, kind: string, key: string}> */
    private function hostsOf(Tenant $tenant): array
    {
        return EntryPoint::query()->where('tenant_id', $tenant->id)->where('status', EntryPoint::ACTIVE)->orderBy('host')->get()
            ->map(fn (EntryPoint $entry) => ['host' => $entry->host, 'origin' => TenantNaming::origin($entry->host), 'kind' => $entry->kind, 'key' => $entry->key])
            ->all();
    }

    /** @param list<array{host: string, origin: string, kind: string, key: string}> $hosts */
    private static function hashOf(array $hosts): string
    {
        return hash('sha256', (string) json_encode($hosts));
    }
}
