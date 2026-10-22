<?php

namespace Ulams\AuthoringMetrics\Tests;

use Carbon\CarbonImmutable;
use Ulams\AuthoringMetrics\Database\Seeders\AuthoringMetricsPermissionSeeder;
use Ulams\AuthoringMetrics\UlamsAuthoringMetricsServiceProvider;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Tests\TestCase as BuilderTestCase;

/** The Course Builder test case (users, Passport, fake driver) with authoring metrics loaded. */
class TestCase extends BuilderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthoringMetricsPermissionSeeder::class);
        // other suites commit runs; the backfill counts every run of the database, so start from none (rolled back with the test)
        Run::query()->delete();
        Event::query()->delete();
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsAuthoringMetricsServiceProvider::class];
    }

    protected function bareSession(): Session
    {
        return Session::query()->create(['author_id' => $this->author()->getKey(), 'status' => Session::DRAFT]);
    }

    protected function run_(Session $session, string $kind, string $status, ?\Carbon\CarbonInterface $start, ?\Carbon\CarbonInterface $end): Run
    {
        return Run::query()->create(['session_id' => $session->id, 'kind' => $kind, 'status' => $status, 'started_at' => $start, 'finished_at' => $end]);
    }

    /** An AG-UI event row at an exact moment (what the pruned-or-not log would hold). */
    protected function logged(Run $run, string $type, CarbonImmutable $at, array $payload = []): void
    {
        Event::query()->create([
            'session_id' => $run->session_id, 'run_id' => $run->id, 'type' => $type,
            'payload' => ['type' => $type] + $payload + ['timestamp' => $at->getTimestampMs()],
        ]);
    }

    protected function t(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-10-20 {$time}", config('app.timezone'));
    }
}
