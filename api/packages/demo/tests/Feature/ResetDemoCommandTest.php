<?php

namespace Ulams\Demo\Tests\Feature;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Ulams\H5P\Exceptions\H5PServiceException;
use Mockery;
use Ulams\Demo\Services\H5PContentCleaner;
use Ulams\Demo\Support\DemoBaseline;
use Ulams\Demo\Tests\TestCase;
use Ulams\Settings\Models\Setting;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;

/**
 * The reset itself runs in child processes (FakeTenantCommandRunner records them), so this
 * test never wipes the test database.
 */
class ResetDemoCommandTest extends TestCase
{
    private string $storage;
    private FakeTenantCommandRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir() . '/ulams-demo-test-' . uniqid();
        File::ensureDirectoryExists($this->storage . '/app');
        $this->app->useStoragePath($this->storage);
        $this->runner = new FakeTenantCommandRunner($this->storage);
        $this->app->instance(TenantCommandRunnerContract::class, $this->runner);
        config([
            'cache.default' => 'array',
            'app.url' => 'http://coffee.localhost',
            'app.name' => 'The Coffee Atlas',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function testRefusesOnThePlatform(): void
    {
        $this->artisan('ulams:demo:reset', ['--force' => true])
            ->expectsOutputToContain('only runs inside a tenant')
            ->assertExitCode(1);

        $this->assertSame([], $this->runner->calls);
    }

    public function testRefusesWithoutConfirmation(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);

        $this->artisan('ulams:demo:reset')
            ->expectsConfirmation('This deletes every record of coffee.localhost. Continue?', 'no')
            ->assertExitCode(1);

        $this->assertSame([], $this->runner->calls);
    }

    public function testWipesAndReseedsTheTenantFromItsBaseline(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee', 'ulams_demo.admin_email' => 'admin@coffee.ulams.app']);
        foreach ([['global', 'companyName', 'The Coffee Atlas'], ['theme', 'theme', 'coffee'], ['theme', 'accent', '#C2552D'], ['global', 'frontURL', 'http://coffee.app.localhost']] as [$group, $key, $value]) {
            Setting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value, 'type' => 'text', 'public' => true, 'enumerable' => true, 'sort' => 0]);
        }

        $this->artisan('ulams:demo:reset', ['--force' => true])->assertExitCode(0);

        $this->assertSame(
            ['migrate:fresh', 'passport:client', 'db:seed', 'ulams:tenant:seed-demo', 'ulams:demo:seed'],
            $this->runner->commands()
        );
        $this->assertSame(['coffee.localhost'], array_values(array_unique(array_column($this->runner->calls, 'host'))));
        $this->assertSame(['db:seed', '--class=PermissionsSeeder', '--force'], $this->runner->calls[2]['arguments']);
        $this->assertSame([
            'ulams:tenant:seed-demo',
            '--users=5',
            '--name=The Coffee Atlas',
            '--theme=coffee',
            '--accent=#C2552D',
            '--front-url=http://coffee.app.localhost',
            '--email-domain=coffee.ulams.app',
        ], $this->runner->calls[3]['arguments']);
        $this->assertFileExists($this->storage . '/' . DemoBaseline::FILE);
    }

    public function testLaterResetsReuseTheStoredBaseline(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        Setting::query()->updateOrCreate(['group' => 'theme', 'key' => 'theme'], ['value' => 'coffee', 'type' => 'text', 'public' => true, 'enumerable' => true, 'sort' => 0]);
        $this->artisan('ulams:demo:reset', ['--force' => true])->assertExitCode(0);

        // an admin of the demo changed the theme; the next reset restores the baseline
        Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->update(['value' => 'nightsky']);
        $this->runner->calls = [];
        $this->artisan('ulams:demo:reset', ['--force' => true])->assertExitCode(0);

        $this->assertContains('--theme=coffee', $this->runner->calls[3]['arguments']);
    }

    public function testDeletesTheTenantsH5PContentBeforeTheWipe(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $cleaner = Mockery::mock(H5PContentCleaner::class);
        $cleaner->shouldReceive('available')->andReturnTrue();
        $cleaner->shouldReceive('clean')->once()->andReturnUsing(function () {
            $this->assertSame([], $this->runner->calls, 'H5P cleanup runs before migrate:fresh');

            return ['deleted' => 4, 'missing' => 0, 'failed' => [], 'orphan_contents' => 1, 'orphan_files' => 3];
        });
        $this->app->instance(H5PContentCleaner::class, $cleaner);

        $this->artisan('ulams:demo:reset', ['--force' => true])
            ->expectsOutputToContain('4 content(s) deleted')
            ->assertExitCode(0);

        $this->assertSame('migrate:fresh', $this->runner->commands()[0]);
    }

    public function testAnUnreachableH5PServiceDoesNotStopTheReset(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $cleaner = Mockery::mock(H5PContentCleaner::class);
        $cleaner->shouldReceive('available')->andReturnTrue();
        $cleaner->shouldReceive('clean')->once()->andThrow(new H5PServiceException('H5P service is unreachable: refused'));
        $this->app->instance(H5PContentCleaner::class, $cleaner);

        $this->artisan('ulams:demo:reset', ['--force' => true])
            ->expectsOutputToContain('the next reset retries')
            ->assertExitCode(0);

        $this->assertCount(5, $this->runner->calls);
    }

    public function testRecreatesTheLtiKeysWhenTheLtiPackageIsInstalled(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->registerCommand(new class extends \Illuminate\Console\Command {
            protected $signature = 'ulams:lti:rotate-keys {--init}';

            public function handle(): int
            {
                return self::SUCCESS;
            }
        });

        $this->artisan('ulams:demo:reset', ['--force' => true])->assertExitCode(0);

        $this->assertSame(
            ['migrate:fresh', 'passport:client', 'db:seed', 'ulams:lti:rotate-keys', 'ulams:tenant:seed-demo', 'ulams:demo:seed'],
            $this->runner->commands()
        );
        $this->assertSame(['ulams:lti:rotate-keys', '--init'], $this->runner->calls[3]['arguments']);
    }

    public function testAFailingStepFailsTheReset(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $this->runner->failOn['migrate:fresh'] = true;

        $this->artisan('ulams:demo:reset', ['--force' => true])
            ->expectsOutputToContain('Demo reset failed')
            ->assertExitCode(1);

        $this->assertSame(['migrate:fresh'], $this->runner->commands());
    }

    public function testResetIsScheduledHourly(): void
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (ScheduledEvent $event) => str_contains((string) $event->command, 'ulams:demo:reset')
        ));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events[0]->expression);
        $this->assertStringContainsString('--force', (string) $events[0]->command);
    }
}
