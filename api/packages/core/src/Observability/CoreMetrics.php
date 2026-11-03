<?php

namespace Ulams\Core\Observability;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Spatie\Health\ResultStores\ResultStore;

/**
 * Gauges the core owns, computed when Prometheus scrapes: queues, failed jobs, heartbeats, database
 * connections and the outcome of the latest health checks (which also carries storage and the
 * PDF/MJML services).
 */
class CoreMetrics
{
    /** @return list<Metric> */
    public function __invoke(): array
    {
        return array_values(array_filter([
            Metric::gauge('ulams_up', 'The application answered the scrape.', [[1]]),
            $this->queueSizes(),
            $this->queueAges(),
            $this->failedJobs(),
            $this->heartbeats(),
            $this->databaseConnections(),
            $this->healthChecks(),
        ]));
    }

    /** @return list<string> */
    public static function queues(): array
    {
        return array_values(array_filter((array) config('ulams.core.metrics.queues', [])));
    }

    private function driver(): ?string
    {
        $connection = (string) config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        return in_array($driver, ['database', 'redis'], true) ? $driver : null;
    }

    private function queueSizes(): ?Metric
    {
        if ($this->driver() === null) {
            return null;
        }
        $samples = [];
        foreach (self::queues() as $queue) {
            $samples[] = [(int) Queue::connection()->size($queue), ['queue' => $queue]];
        }

        return Metric::gauge('ulams_queue_size', 'Jobs waiting in a queue (pending, delayed and reserved).', $samples);
    }

    private function queueAges(): ?Metric
    {
        $driver = $this->driver();
        if ($driver === null) {
            return null;
        }
        $samples = [];
        foreach (self::queues() as $queue) {
            $age = $driver === 'database' ? $this->oldestDatabaseJob($queue) : $this->oldestRedisJob($queue);
            $samples[] = [$age ?? 0, ['queue' => $queue]];
        }

        return Metric::gauge('ulams_queue_oldest_job_age_seconds', 'Age of the oldest pending job in a queue, 0 when it is empty.', $samples);
    }

    private function oldestDatabaseJob(string $queue): ?int
    {
        $table = (string) config('queue.connections.' . config('queue.default') . '.table', 'jobs');
        $available = DB::table($table)->where('queue', $queue)->whereNull('reserved_at')->min('available_at');

        return $available === null ? null : max(0, time() - (int) $available);
    }

    private function oldestRedisJob(string $queue): ?int
    {
        $connection = (string) config('queue.connections.' . config('queue.default') . '.connection', 'default');
        $payload = Redis::connection($connection)->lindex('queues:' . $queue, 0);
        $pushedAt = is_string($payload) ? (json_decode($payload, true)['pushedAt'] ?? null) : null;

        return is_numeric($pushedAt) ? max(0, (int) (microtime(true) - (float) $pushedAt)) : null;
    }

    private function failedJobs(): ?Metric
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');

        return Metric::gauge('ulams_failed_jobs', 'Rows in the failed jobs table.', [[(int) DB::table($table)->count()]]);
    }

    private function heartbeats(): Metric
    {
        $samples = [];
        foreach ([Heartbeat::SCHEDULER, Heartbeat::QUEUE] as $name) {
            $age = Heartbeat::age($name);
            if ($age !== null) {
                $samples[] = [$age, ['name' => $name]];
            }
        }

        return Metric::gauge('ulams_heartbeat_age_seconds', 'Seconds since the scheduler or a queue worker last beat; absent when it never did.', $samples);
    }

    private function databaseConnections(): ?Metric
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }
        $rows = DB::select('select coalesce(state, \'background\') as state, count(*) as total from pg_stat_activity where datname = current_database() group by 1');

        return Metric::gauge('ulams_db_connections', 'Connections to this database by state (pg_stat_activity).', array_map(
            fn ($row) => [(int) $row->total, ['state' => (string) $row->state]],
            $rows
        ));
    }

    private function healthChecks(): ?Metric
    {
        if (!class_exists(ResultStore::class)) {
            return null;
        }
        $results = app(ResultStore::class)->latestResults();
        if ($results === null) {
            return null;
        }
        $samples = [];
        foreach ($results->storedCheckResults as $result) {
            $samples[] = [$result->status === 'ok' ? 1 : 0, ['check' => (string) $result->name]];
        }

        return Metric::gauge('ulams_health_check_ok', 'Latest result of a health check, 1 for ok (checks run every minute).', $samples);
    }
}
