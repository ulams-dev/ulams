<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\File;
use Mockery;
use Mockery\MockInterface;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantTemplate;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;

/** Tenant templates (ADR 0141, amendment): the starting point of a new tenant, never a rule for an existing one. */
class TenantTemplateTest extends TestCase
{
    private string $storage;
    private FakeTenantCommandRunner $runner;
    private MockInterface $domains;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir() . '/ulams-template-test-' . uniqid();
        $this->runner = new FakeTenantCommandRunner($this->storage);
        $database = Mockery::mock(DatabaseProvisionerContract::class);
        $database->shouldReceive('ensure');
        $buckets = Mockery::mock(BucketProvisionerContract::class);
        $buckets->shouldReceive('ensure');
        $this->domains = Mockery::mock(DomainRegistryContract::class);
        $this->domains->shouldReceive('storagePath')->andReturn($this->storage);
        $this->domains->shouldReceive('add');
        $this->app->instance(TenantCommandRunnerContract::class, $this->runner);
        $this->app->instance(DatabaseProvisionerContract::class, $database);
        $this->app->instance(BucketProvisionerContract::class, $buckets);
        $this->app->instance(DomainRegistryContract::class, $this->domains);
        $this->app->forgetInstance(TenantProvisioner::class);
        $this->app->forgetInstance(TenantLifecycle::class);
        config(['ulams_tenancy.inherited_env' => ['AI_DRIVER' => 'fake', 'AI_MODEL_DEFAULT' => '']]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function seedDemoArguments(): array
    {
        $call = collect($this->runner->calls)->first(fn ($c) => $c['arguments'][0] === 'ulams:tenant:seed-demo');

        return $call['arguments'] ?? [];
    }

    public function testACommandCreatesChangesAndRemovesATemplate(): void
    {
        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--name' => 'Policy academy', '--theme' => 'coffee', '--accent' => '#c2552d', '--users' => 2, '--demo' => 'off', '--set' => ['AI_DRIVER=fake']])->assertExitCode(0);

        $template = TenantTemplate::query()->firstWhere('slug', 'policy-academy');
        $this->assertSame(['Policy academy', 'coffee', '#C2552D', 2, false], [$template->name, $template->theme, $template->accent, $template->users, $template->demo]);
        $this->assertSame(['AI_DRIVER' => 'fake'], $template->env_overrides);

        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--theme' => 'nightsky'])->assertExitCode(0);
        $template->refresh();
        $this->assertSame(['nightsky', '#C2552D', 2], [$template->theme, $template->accent, $template->users], 'only the given fields change');

        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--delete' => true])->assertExitCode(0);
        $this->assertNull(TenantTemplate::query()->firstWhere('slug', 'policy-academy'));
    }

    public function testInvalidTemplatesAreRefused(): void
    {
        $this->artisan('ulams:tenant:template', ['slug' => 'Bad Slug'])->assertExitCode(1);
        $this->artisan('ulams:tenant:template', ['slug' => 'ok-slug', '--accent' => 'red'])->assertExitCode(1);
        $this->artisan('ulams:tenant:template', ['slug' => 'ok-slug', '--users' => 99])->assertExitCode(1);
        $this->artisan('ulams:tenant:template', ['slug' => 'ok-slug', '--set' => ['DB_PASSWORD=x']])->assertExitCode(1);
        $this->artisan('ulams:tenant:template', ['slug' => 'ok-slug', '--set' => ['AI_DRIVER=']])->assertExitCode(1);
        $this->assertSame(0, TenantTemplate::query()->count());
    }

    public function testANewTenantStartsFromTheTemplateAndExplicitOptionsWin(): void
    {
        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--name' => 'Policy academy', '--theme' => 'coffee', '--accent' => '#C2552D', '--users' => 2, '--set' => ['AI_DRIVER=fake']])->assertExitCode(0);

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--template' => 'policy-academy', '--accent' => '#112233'])->assertExitCode(0);

        $tenant = Tenant::query()->firstWhere('slug', 'acme');
        $this->assertSame(['Policy academy', 'coffee', '#112233'], [$tenant->name, $tenant->theme, $tenant->accent]);
        $this->assertSame(['AI_DRIVER' => 'fake'], $tenant->env_overrides);
        $demo = $this->seedDemoArguments();
        $this->assertContains('--users=2', $demo, 'the template decides the demo users unless --users is given');
        $this->assertContains('--theme=coffee', $demo);
        $this->assertContains('--accent=#112233', $demo);
    }

    public function testTheUsersOptionBeatsTheTemplate(): void
    {
        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--users' => 2])->assertExitCode(0);

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--template' => 'policy-academy', '--users' => 0])->assertExitCode(0);

        $this->assertContains('--users=0', $this->seedDemoArguments());
    }

    public function testATemplateNeverChangesAnExistingTenant(): void
    {
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--name' => 'Acme Academy', '--theme' => 'nightsky'])->assertExitCode(0);
        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--name' => 'Policy academy', '--theme' => 'coffee', '--set' => ['AI_DRIVER=fake']])->assertExitCode(0);

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--template' => 'policy-academy'])->assertExitCode(0);

        $tenant = Tenant::query()->firstWhere('slug', 'acme');
        $this->assertSame(['Acme Academy', 'nightsky'], [$tenant->name, $tenant->theme]);
        $this->assertNull($tenant->env_overrides);
    }

    public function testAnUnknownTemplateStopsBeforeAnythingIsCreated(): void
    {
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--template' => 'nope'])->expectsOutputToContain("Unknown tenant template 'nope'")->assertExitCode(1);

        $this->assertNull(Tenant::query()->firstWhere('slug', 'acme'));
        $this->assertSame([], $this->runner->calls);
    }

    public function testChangingATemplateLaterLeavesTheTenantsMadeFromItAlone(): void
    {
        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--theme' => 'coffee'])->assertExitCode(0);
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--template' => 'policy-academy'])->assertExitCode(0);

        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--theme' => 'nightsky'])->assertExitCode(0);
        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--delete' => true])->assertExitCode(0);

        $this->assertSame('coffee', Tenant::query()->firstWhere('slug', 'acme')->theme);
    }

    public function testTemplateSettingsAreEncryptedAtRest(): void
    {
        $this->artisan('ulams:tenant:template', ['slug' => 'policy-academy', '--set' => ['AI_DRIVER=fake']])->assertExitCode(0);

        $raw = \Illuminate\Support\Facades\DB::table('tenant_templates')->where('slug', 'policy-academy')->value('env_overrides');
        $this->assertStringNotContainsString('fake', (string) $raw);
    }
}
