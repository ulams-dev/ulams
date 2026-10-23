<?php

namespace Ulams\Reporting\Tests;

use Spatie\Permission\Models\Role;
use Ulams\Ai\Tests\TestCase as AiTestCase;
use Ulams\Core\Enums\UserRole;
use Ulams\Reporting\Database\Seeders\ReportingPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Tests\Support\LearnerCallsDataset;
use Ulams\Reporting\Tests\Support\ScopedCallsDataset;
use Ulams\Reporting\UlamsReportingServiceProvider;

/** The AI test case (users, Passport, fake driver, `ai_calls`) with the reporting package loaded. */
class TestCase extends AiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([UserRole::ADMIN, UserRole::TUTOR, UserRole::STUDENT] as $role) {
            Role::findOrCreate($role, 'api');
        }
        $this->seed(ReportingPermissionSeeder::class);
    }

    /** The test datasets that behave like learner datasets (`learner_calls`, and `learner_stats` which is aggregate-only) and `my_calls`, which shows a viewer their own rows only. */
    protected function registerLearnerDatasets(): void
    {
        $registry = $this->app->make(DatasetRegistry::class);
        $registry->register(new LearnerCallsDataset());
        $registry->register(new LearnerCallsDataset(aggregateOnly: true, key: 'learner_stats'));
        $registry->register(new ScopedCallsDataset());
    }

    /** One `ai_calls` row per learner id: what the learner dataset counts as that learner's activity. */
    protected function calls(string $task, iterable $learners, array $extra = []): void
    {
        foreach ($learners as $learner) {
            DB::table('ai_calls')->insert($extra + [
                'id' => (string) Str::ulid(), 'task' => $task, 'driver' => 'fake', 'model_requested' => 'm', 'status' => 'ok', 'user_id' => $learner,
                'input_tokens' => 10, 'output_tokens' => 5, 'cost_micro_usd' => 1000000, 'latency_ms' => 10, 'created_at' => now(),
            ]);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsReportingServiceProvider::class];
    }
}
