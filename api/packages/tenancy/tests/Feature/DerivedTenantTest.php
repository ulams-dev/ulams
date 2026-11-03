<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\File;
use Mockery;
use Mockery\MockInterface;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantDerivation;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Support\RedisKeyPurger;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;

/** Tenants created by another tenant for a purpose: the record, the quota, idempotency and cleanup (ADR 0141, amendment). */
class DerivedTenantTest extends TestCase
{
    private string $storage;
    private FakeTenantCommandRunner $runner;
    private MockInterface $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir() . '/ulams-derived-test-' . uniqid();
        $this->runner = new FakeTenantCommandRunner($this->storage);
        $this->database = Mockery::mock(DatabaseProvisionerContract::class);
        $this->database->shouldReceive('ensure');
        $this->database->shouldReceive('drop');
        $buckets = Mockery::mock(BucketProvisionerContract::class);
        $buckets->shouldReceive('ensure');
        $buckets->shouldReceive('purge')->zeroOrMoreTimes();
        $buckets->shouldReceive('drop')->zeroOrMoreTimes();
        $buckets->shouldReceive('delete')->zeroOrMoreTimes();
        $domains = Mockery::mock(DomainRegistryContract::class);
        $domains->shouldReceive('storagePath')->andReturn($this->storage);
        $domains->shouldReceive('add');
        $domains->shouldReceive('remove')->zeroOrMoreTimes();
        $redis = Mockery::mock(RedisKeyPurger::class);
        $redis->shouldReceive('purge')->zeroOrMoreTimes();
        $this->app->instance(TenantCommandRunnerContract::class, $this->runner);
        $this->app->instance(DatabaseProvisionerContract::class, $this->database);
        $this->app->instance(BucketProvisionerContract::class, $buckets);
        $this->app->instance(DomainRegistryContract::class, $domains);
        $this->app->instance(RedisKeyPurger::class, $redis);
        $this->app->forgetInstance(TenantProvisioner::class);
        $this->app->forgetInstance(TenantLifecycle::class);
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--users' => 0])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function derive(string $slug, string $reference, array $extra = [])
    {
        return $this->artisan('ulams:tenant:create', ['slug' => $slug, '--users' => 0, '--derived-from' => 'acme', '--purpose' => 'policy_department', '--reference' => $reference, '--requested-by' => 'owner@example.test'] + $extra);
    }

    public function testADerivedTenantIsRecordedWithWhoAskedAndForWhat(): void
    {
        $this->derive('acmesales', 'policy-1:variant-1', ['--template' => null])->assertExitCode(0);

        $row = TenantDerivation::query()->sole();
        $this->assertSame(['acmesales', 'acme', 'policy_department', 'policy-1:variant-1', 'owner@example.test', 'active'], [$row->tenant_slug, $row->parent_slug, $row->purpose, $row->reference, $row->requested_by, $row->status]);
        $this->assertSame(Tenant::STATUS_ACTIVE, Tenant::query()->firstWhere('slug', 'acmesales')->status);
    }

    public function testAskingAgainForTheSameReferenceResumesTheSameTenantAndRecordsItOnce(): void
    {
        $this->derive('acmesales', 'policy-1:variant-1')->assertExitCode(0);
        $creates = count($this->runner->calls);

        $this->derive('acmesales', 'policy-1:variant-1')->assertExitCode(0);

        $this->assertSame(1, TenantDerivation::query()->count());
        $this->assertSame(1, Tenant::query()->where('slug', 'acmesales')->count());
        $this->assertSame($creates, count($this->runner->calls), 'finished steps are skipped');
    }

    public function testAReferenceHasOneTenantAndATenantHasOnePurpose(): void
    {
        $this->derive('acmesales', 'policy-1:variant-1')->assertExitCode(0);

        $this->derive('acmeother', 'policy-1:variant-1')->expectsOutputToContain("already has the tenant 'acmesales'")->assertExitCode(1);
        $this->derive('acmesales', 'policy-2:variant-1')->expectsOutputToContain('created for another purpose')->assertExitCode(1);

        $this->assertNull(Tenant::query()->firstWhere('slug', 'acmeother'));
        $this->assertSame(1, TenantDerivation::query()->count());
    }

    public function testAParentCannotGoOverItsQuota(): void
    {
        config(['ulams_tenancy.derived.max_per_parent' => 2]);
        $this->derive('acmea', 'r1')->assertExitCode(0);
        $this->derive('acmeb', 'r2')->assertExitCode(0);

        $this->derive('acmec', 'r3')->expectsOutputToContain('the limit is 2')->assertExitCode(1);

        $this->assertNull(Tenant::query()->firstWhere('slug', 'acmec'), 'the refusal happens before a tenant row exists');
        $this->assertSame(2, TenantDerivation::query()->count());
    }

    public function testADerivedTenantCannotCreateTenantsAndTheParentMustExist(): void
    {
        $this->derive('acmesales', 'r1')->assertExitCode(0);

        $this->artisan('ulams:tenant:create', ['slug' => 'grandchild', '--derived-from' => 'acmesales', '--purpose' => 'policy_department', '--reference' => 'r'])->expectsOutputToContain('cannot create tenants')->assertExitCode(1);
        $this->artisan('ulams:tenant:create', ['slug' => 'orphan', '--derived-from' => 'nobody', '--purpose' => 'policy_department', '--reference' => 'r'])->expectsOutputToContain("'nobody' does not exist")->assertExitCode(1);
        $this->artisan('ulams:tenant:create', ['slug' => 'loose', '--purpose' => 'policy_department'])->expectsOutputToContain('go with --derived-from')->assertExitCode(1);
    }

    public function testARefusedOptionLeavesNoRecordBehind(): void
    {
        $this->derive('acmesales', 'r1', ['--accent' => 'red'])->assertExitCode(1);

        $this->assertSame(0, TenantDerivation::query()->count());
        $this->assertNull(Tenant::query()->firstWhere('slug', 'acmesales'));
    }

    public function testAParentWithDerivedTenantsIsNotDeletedUnlessTheyGoWithIt(): void
    {
        $this->derive('acmesales', 'r1')->assertExitCode(0);

        $this->artisan('ulams:tenant:delete', ['slug' => 'acme', '--force' => true])->expectsOutputToContain('has tenants created from it')->assertExitCode(1);
        $this->assertNotNull(Tenant::query()->firstWhere('slug', 'acme'));
        $this->assertNotNull(Tenant::query()->firstWhere('slug', 'acmesales'));

        $this->artisan('ulams:tenant:delete', ['slug' => 'acme', '--force' => true, '--with-derived' => true])->assertExitCode(0);
        $this->assertNull(Tenant::query()->firstWhere('slug', 'acme'));
        $this->assertNull(Tenant::query()->firstWhere('slug', 'acmesales'));
        $this->assertSame('deleted', TenantDerivation::query()->sole()->status);
    }

    public function testDeletingADerivedTenantKeepsTheRecordAndFreesTheQuotaAndTheReference(): void
    {
        config(['ulams_tenancy.derived.max_per_parent' => 1]);
        $this->derive('acmesales', 'r1')->assertExitCode(0);
        $this->artisan('ulams:tenant:delete', ['slug' => 'acmesales', '--force' => true])->assertExitCode(0);

        $row = TenantDerivation::query()->sole();
        $this->assertSame('deleted', $row->status);
        $this->assertNotNull($row->deleted_at);

        $this->derive('acmesales', 'r1')->assertExitCode(0);
        $this->assertSame('active', TenantDerivation::query()->sole()->status);
    }
}
