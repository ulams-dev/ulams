<?php

namespace Ulams\Tenancy\Observability;

use Illuminate\Redis\RedisManager;
use Ulams\Core\Observability\Metric;
use Ulams\Tenancy\Models\Tenant;

/**
 * Tenants by status, and (with the Redis queue) the depth of each active tenant's queues. A tenant's
 * Redis keys carry its own prefix, so they are read through a connection with that prefix.
 */
class TenantMetrics
{
    /** @return list<Metric> */
    public function __invoke(): array
    {
        $counts = Tenant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();
        $samples = [];
        foreach ([Tenant::STATUS_ACTIVE, Tenant::STATUS_PROVISIONING, Tenant::STATUS_FAILED] as $status) {
            $samples[] = [(int) ($counts[$status] ?? 0), ['status' => $status]];
        }
        $metrics = [Metric::gauge('ulams_tenants', 'Provisioned tenants by status.', $samples)];

        if (config('queue.connections.' . config('queue.default') . '.driver') === 'redis') {
            $metrics[] = $this->tenantQueues();
        }

        return $metrics;
    }

    private function tenantQueues(): Metric
    {
        $config = (array) config('database.redis');
        $samples = [];
        foreach (Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->get(['slug', 'redis_prefix']) as $tenant) {
            $own = $config;
            $own['options'] = array_merge((array) ($config['options'] ?? []), ['prefix' => $tenant->redis_prefix]);
            $connection = (new RedisManager(app(), (string) ($config['client'] ?? 'phpredis'), $own))->connection();
            foreach (\Ulams\Core\Observability\CoreMetrics::queues() as $queue) {
                $key = 'queues:' . $queue;
                $size = (int) $connection->llen($key) + (int) $connection->zcard($key . ':delayed') + (int) $connection->zcard($key . ':reserved');
                $samples[] = [$size, ['tenant' => $tenant->slug, 'queue' => $queue]];
            }
        }

        return Metric::gauge('ulams_tenant_queue_size', 'Jobs waiting in a queue of an active tenant (Redis queue only).', $samples);
    }
}
