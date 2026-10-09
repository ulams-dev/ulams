<?php

namespace Tests\Integrations;

use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;
use Ulams\Adapt\Jobs\BuildAdaptSource;
use Ulams\CourseBuilder\Jobs\MoveToNewSiteJob;
use Ulams\CourseBuilder\Jobs\RunJob;
use Ulams\CourseBuilder\Jobs\StepJob;
use Ulams\Tenancy\Jobs\DeleteTenantJob;
use Ulams\Tenancy\Jobs\ProvisionTenantJob;
use Ulams\CoursesImportExport\Jobs\CloneCourse;
use Ulams\LivingCourse\Jobs\AnalyseGroupJob;
use Ulams\LivingCourse\Jobs\CheckSourceJob;
use Ulams\LivingCourse\Jobs\ProgressRulesJob;
use Ulams\Video\Jobs\ProcessVideo;

/**
 * A queued job that runs longer than the connection's `retry_after` is handed to a second worker
 * while the first is still running it: a double LLM call (double cost), a double clone. Every long
 * job therefore runs on a connection whose retry_after is above its `$timeout` plus a margin, in
 * both the database and the redis variant (ADR 0083).
 */
class QueueRetryAfterConfigTest extends TestCase
{
    /** seconds the connection's retry_after must exceed the job timeout by */
    private const MARGIN = 300;

    /** job => [package config file that routes it, key of the connection, key of the queue] */
    private const ROUTES = [
        RunJob::class => ['packages/course-builder/config/course_builder.php', 'queue_connection', 'queue'],
        StepJob::class => ['packages/course-builder/config/course_builder.php', 'queue_connection', 'queue'],
        MoveToNewSiteJob::class => ['packages/course-builder/config/course_builder.php', 'queue_connection', 'queue'],
        AnalyseGroupJob::class => ['packages/living-course/config/living_course.php', 'queue_connection', 'queue'],
        CheckSourceJob::class => ['packages/living-course/config/living_course.php', 'queue_connection', 'queue'],
        ProgressRulesJob::class => ['packages/living-course/config/living_course.php', 'queue_connection', 'queue'],
        BuildAdaptSource::class => ['packages/adapt/src/config.php', 'queue_connection', 'queue'],
        ProcessVideo::class => ['packages/video/src/config.php', 'queue_connection', 'queue'],
        CloneCourse::class => ['config/queue.php', 'long_job.connection', 'long_job.queue'],
        ProvisionTenantJob::class => ['config/queue.php', 'long_job.connection', 'long_job.queue'],
        DeleteTenantJob::class => ['config/queue.php', 'long_job.connection', 'long_job.queue'],
    ];

    public static function jobsAndDrivers(): array
    {
        $cases = [];
        foreach (array_keys(self::ROUTES) as $job) {
            foreach (['database', 'redis'] as $driver) {
                $cases[class_basename($job) . ' on ' . $driver] = [$job, $driver];
            }
        }

        return $cases;
    }

    #[DataProvider('jobsAndDrivers')]
    public function testRetryAfterIsAboveTheJobTimeout(string $job, string $driver): void
    {
        [$file, $connectionKey, $queueKey] = self::ROUTES[$job];
        $config = $this->configWith($file, ['QUEUE_CONNECTION' => $driver]);
        $connection = data_get($config, $connectionKey);
        $queue = data_get($config, $queueKey);

        $this->assertNotNull($connection, "$job has no queue connection with QUEUE_CONNECTION=$driver");
        $this->assertStringStartsWith($driver . '-', $connection, "$job must use the $driver variant");
        $this->assertNotContains($queue, [null, 'default'], "$job needs its own queue name: a queue name is shared between connections of one driver, and the worker that pops it decides retry_after");

        $connections = config('queue.connections');
        $this->assertArrayHasKey($connection, $connections);
        $this->assertSame($driver, $connections[$connection]['driver']);

        $timeout = $this->timeoutOf($job);
        $this->assertGreaterThanOrEqual(1, $timeout, "$job must declare \$timeout");
        $this->assertGreaterThan(
            $timeout + self::MARGIN,
            $connections[$connection]['retry_after'],
            "retry_after of $connection must exceed the $job timeout ($timeout s) plus a margin"
        );
    }

    /** a job that declares a timeout above the default connections' retry_after (90 s) must be routed above */
    public function testEveryLongJobIsRouted(): void
    {
        $default = min(config('queue.connections.database.retry_after'), config('queue.connections.redis.retry_after'));
        $routed = array_keys(self::ROUTES);
        $unrouted = [];
        foreach (glob(base_path('packages/*/src/Jobs/*.php')) as $file) {
            $class = $this->classOf($file);
            if ($class === null || ! class_exists($class)) {
                continue;
            }
            $timeout = $this->timeoutOf($class);
            if ($timeout >= $default && ! in_array($class, $routed, true)) {
                $unrouted[] = "$class ($timeout s)";
            }
        }
        $this->assertSame([], $unrouted, 'jobs that run longer than retry_after of the default connection: add a route (queue connection) and list it in ROUTES');
    }

    /** the worker of each long queue must give up at the job timeout, before retry_after */
    #[DataProvider('workerLines')]
    public function testWorkerTimeoutMatchesTheQueue(string $program, int $jobTimeout, string $connectionSuffix): void
    {
        $script = file_get_contents(base_path('workers.sh'));
        $this->assertSame(1, preg_match('/^\s*' . $program . '\) echo (.+?) ;;$/m', $script, $m), "workers.sh has no $program worker");
        $this->assertSame(1, preg_match('/--timeout=(\d+)/', $m[1], $t));
        $this->assertGreaterThanOrEqual($jobTimeout, (int) $t[1], 'worker --timeout below the job timeout would kill the job early');
        foreach (['database', 'redis'] as $driver) {
            $this->assertGreaterThan((int) $t[1], config("queue.connections.$driver-$connectionSuffix.retry_after"));
        }
        $this->assertStringContainsString('-' . $connectionSuffix, $m[1]);
    }

    public static function workerLines(): array
    {
        return [
            'builder queue' => ['builder', 1800, 'builder'],
            'long-job queue' => ['long', 18000, 'long-job'],
        ];
    }

    public function testHorizonSupervisorsMatchTheQueues(): void
    {
        foreach (['production', 'local', 'stage'] as $env) {
            $supervisors = config("horizon.environments.$env");
            $this->assertSame(1800, $supervisors['supervisor-builder']['timeout']);
            $this->assertSame('redis-builder', $supervisors['supervisor-builder']['connection']);
            $this->assertSame(18000, $supervisors['supervisor-long-job']['timeout']);
            $this->assertSame('redis-long-job', $supervisors['supervisor-long-job']['connection']);
        }
    }

    private function timeoutOf(string $class): int
    {
        return (int) ((new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? 0);
    }

    private function classOf(string $file): ?string
    {
        $code = file_get_contents($file);
        if (! preg_match('/^namespace\s+([^;]+);/m', $code, $ns) || ! preg_match('/^class\s+(\w+)/m', $code, $cls)) {
            return null;
        }

        return $ns[1] . '\\' . $cls[1];
    }

    /** a package config file evaluated with other environment variables (its defaults depend on QUEUE_CONNECTION) */
    private function configWith(string $file, array $env): array
    {
        $keys = array_unique(array_merge(array_keys($env), [
            'COURSE_BUILDER_QUEUE_CONNECTION', 'COURSE_BUILDER_QUEUE', 'LIVING_COURSE_QUEUE_CONNECTION',
            'LIVING_COURSE_QUEUE', 'ADAPT_QUEUE_CONNECTION', 'ADAPT_QUEUE', 'VIDEO_QUEUE_CONNECTION', 'VIDEO_QUEUE',
            'LONG_JOB_QUEUE_CONNECTION', 'LONG_JOB_QUEUE',
        ]));
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        foreach ($env as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
        try {
            return require base_path($file);
        } finally {
            foreach ($saved as $key => [$e, $s, $g]) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
                $e === null ?: $_ENV[$key] = $e;
                $s === null ?: $_SERVER[$key] = $s;
                $g === false ?: putenv("$key=$g");
            }
        }
    }
}
