<?php

namespace Ulams\Tenancy\Tests\Feature;

use Ulams\Tenancy\Console\UpgradeCommand;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantUpgradeStep;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;
use Ulams\Tenancy\Upgrade\DefaultUpgradeSteps;
use Ulams\Tenancy\Upgrade\UpgradeContext;
use Ulams\Tenancy\Upgrade\UpgradeSteps;

class UpgradeCommandTest extends TestCase
{
    private FakeTenantCommandRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new FakeTenantCommandRunner(sys_get_temp_dir() . '/ulams-upgrade-test-' . uniqid());
        $this->app->instance(TenantCommandRunnerContract::class, $this->runner);
        UpgradeSteps::flush();
        $this->tenant('alpha');
        $this->tenant('beta');
    }

    protected function tearDown(): void
    {
        UpgradeSteps::flush();
        DefaultUpgradeSteps::register();

        parent::tearDown();
    }

    private function tenant(string $slug, string $status = Tenant::STATUS_ACTIVE): Tenant
    {
        $tenant = Tenant::query()->create(TenantNaming::newTenantAttributes($slug));
        $tenant->markCompleted('env');
        $tenant->status = $status;
        $tenant->save();

        return $tenant;
    }

    /** @return list<string> */
    private function ran(string $command): array
    {
        return array_values(array_map(
            fn (array $call) => $call['host'],
            array_filter($this->runner->calls, fn (array $call) => $call['arguments'][0] === $command)
        ));
    }

    public function testDefaultStepsRunPerTenantAndOneOffStepsOnlyOnce(): void
    {
        DefaultUpgradeSteps::register();

        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha', 'beta']])->assertExitCode(0);
        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha', 'beta']])->assertExitCode(0);

        // every upgrade: migrations and permissions
        $this->assertSame(['alpha.localhost', 'beta.localhost', 'alpha.localhost', 'beta.localhost'], $this->ran('migrate'));
        $this->assertCount(4, $this->ran('db:seed'));
        // one-off: recreate the views, recorded per tenant
        $this->assertSame(['alpha.localhost', 'beta.localhost'], $this->ran('ulams:db:recreate-views'));
        $this->assertDatabaseHas('tenant_upgrade_steps', ['target' => 'alpha', 'step' => 'recreate_views']);
        $this->assertDatabaseHas('tenant_upgrade_steps', ['target' => 'beta', 'step' => 'recreate_views']);
        $this->assertDatabaseMissing('tenant_upgrade_steps', ['step' => 'migrate']);
        // the platform steps are not run for tenants
        $this->assertSame([], $this->ran('ulams:tenant:sync-env'));
    }

    public function testAStepRunsOnceAndAgainWithForceStep(): void
    {
        $count = 0;
        UpgradeSteps::register('count_me', function (UpgradeContext $context) use (&$count): string {
            $count++;

            return "counted for {$context->target}";
        }, since: '1.2');

        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha']])->expectsOutputToContain('counted for alpha')->assertExitCode(0);
        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha']])->assertExitCode(0);
        $this->assertSame(1, $count);
        $this->assertSame('1.2', TenantUpgradeStep::query()->where(['target' => 'alpha', 'step' => 'count_me'])->value('since'));

        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha'], '--force-step' => ['count_me']])->assertExitCode(0);
        $this->assertSame(2, $count);
        $this->assertSame(1, TenantUpgradeStep::query()->where(['target' => 'alpha', 'step' => 'count_me'])->count());
    }

    public function testAFailureInOneTenantDoesNotStopTheOthers(): void
    {
        $later = [];
        UpgradeSteps::register('flaky', function (UpgradeContext $context): void {
            if ($context->target === 'alpha') {
                throw new \RuntimeException('alpha exploded');
            }
        });
        UpgradeSteps::register('after', function (UpgradeContext $context) use (&$later): void {
            $later[] = $context->target;
        });

        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha', 'beta']])
            ->expectsOutputToContain('alpha exploded')
            ->assertExitCode(1);

        // alpha stopped at the failing step and recorded nothing; beta ran everything
        $this->assertSame(['beta'], $later);
        $this->assertDatabaseMissing('tenant_upgrade_steps', ['target' => 'alpha']);
        $this->assertDatabaseHas('tenant_upgrade_steps', ['target' => 'beta', 'step' => 'flaky']);
        $this->assertDatabaseHas('tenant_upgrade_steps', ['target' => 'beta', 'step' => 'after']);

        // a repeat, with the cause gone, finishes alpha
        UpgradeSteps::register('flaky', fn () => null);
        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha', 'beta']])->assertExitCode(0);
        $this->assertSame(['beta', 'alpha'], $later);
    }

    public function testDryRunChangesAndRecordsNothing(): void
    {
        DefaultUpgradeSteps::register();

        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha'], '--dry-run' => true])
            ->expectsOutputToContain('would run recreate_views')
            ->assertExitCode(0);

        $this->assertSame([], $this->runner->calls);
        $this->assertSame(0, TenantUpgradeStep::query()->count());
    }

    public function testAStepWhoseCommandIsMissingIsSkippedAndNotRecorded(): void
    {
        UpgradeSteps::command('future', 'ulams:not-there-yet', requiresCommand: true);

        $this->artisan('ulams:upgrade', ['--tenant' => ['alpha']])
            ->expectsOutputToContain('ulams:not-there-yet is not available')
            ->assertExitCode(0);

        $this->assertSame([], $this->runner->calls);
        $this->assertSame(0, TenantUpgradeStep::query()->count());
    }

    public function testThePlatformIsUpgradedInThisProcessAndRecordedAsPlatform(): void
    {
        $targets = [];
        UpgradeSteps::register('everywhere', function (UpgradeContext $context) use (&$targets): void {
            $targets[] = $context->target . ($context->isPlatform() ? ':platform' : ':tenant');
        });
        UpgradeSteps::register('platform_only', fn () => null, scope: UpgradeSteps::PLATFORM);
        UpgradeSteps::register('tenant_only', fn () => null, scope: UpgradeSteps::TENANT);

        $this->artisan('ulams:upgrade')->assertExitCode(0);

        $this->assertSame(['platform:platform', 'alpha:tenant', 'beta:tenant'], $targets);
        $this->assertDatabaseHas('tenant_upgrade_steps', ['target' => 'platform', 'step' => 'platform_only']);
        $this->assertDatabaseMissing('tenant_upgrade_steps', ['target' => 'platform', 'step' => 'tenant_only']);
        $this->assertDatabaseMissing('tenant_upgrade_steps', ['target' => 'alpha', 'step' => 'platform_only']);

        $this->artisan('ulams:upgrade', ['--platform-only' => true])->assertExitCode(0);
        $this->assertSame(['platform:platform', 'alpha:tenant', 'beta:tenant'], $targets, 'one-off steps do not repeat');
    }

    public function testTenantsThatAreNotActiveAreSkipped(): void
    {
        $this->tenant('gamma', Tenant::STATUS_FAILED);
        DefaultUpgradeSteps::register();

        $this->artisan('ulams:upgrade', ['--tenant' => ['gamma']])
            ->expectsOutputToContain("status is 'failed'")
            ->assertExitCode(0);

        $this->assertSame([], $this->runner->calls);
    }

    public function testUnknownTenantAndUnknownStepAreErrors(): void
    {
        $this->artisan('ulams:upgrade', ['--tenant' => ['nope']])->expectsOutputToContain("Tenant 'nope' does not exist.")->assertExitCode(1);
        $this->artisan('ulams:upgrade', ['--force-step' => ['nope']])->expectsOutputToContain("Unknown step 'nope'")->assertExitCode(1);
    }

    public function testRefusesToRunInsideATenant(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'alpha']);

        $this->artisan('ulams:upgrade')->assertExitCode(1);
    }

    public function testArgumentsBecomeCommandLineTokens(): void
    {
        $this->assertSame(
            ['--force', '--class=PermissionsSeeder', '--a=1', '--a=2', 'positional'],
            UpgradeCommand::tokens(['--force' => true, '--class' => 'PermissionsSeeder', '--skip' => false, '--none' => null, '--a' => [1, 2], 0 => 'positional'])
        );
    }
}
