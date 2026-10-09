<?php

namespace Ulams\Tenancy\Tests\Feature;

use RuntimeException;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\ManualDatabaseProvisioner;
use Ulams\Tenancy\Services\PostgresDatabaseProvisioner;
use Ulams\Tenancy\Tests\TestCase;

class ManualDatabaseProvisionerTest extends TestCase
{
    public function testTheDefaultProvisionerCreatesDatabases(): void
    {
        $this->assertInstanceOf(PostgresDatabaseProvisioner::class, $this->app->make(DatabaseProvisionerContract::class));
    }

    public function testManualModeIsSelectedByConfig(): void
    {
        config(['ulams_tenancy.database_provisioner' => 'manual']);
        $this->app->forgetInstance(DatabaseProvisionerContract::class);

        $this->assertInstanceOf(ManualDatabaseProvisioner::class, $this->app->make(DatabaseProvisionerContract::class));
    }

    public function testUnreachableDatabaseFailsWithoutLeakingThePassword(): void
    {
        config(['database.connections.pgsql.port' => 1, 'database.default' => 'pgsql']);

        try {
            (new ManualDatabaseProvisioner())->ensure('m1_acme', 'm1_acme', 'very-secret-password-123');
            $this->fail('Expected an exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("'m1_acme'", $exception->getMessage());
            $this->assertStringContainsString('devil pgsql db add', $exception->getMessage());
            $this->assertStringNotContainsString('very-secret-password-123', $exception->getMessage());
        }
    }

    public function testDropLeavesTheDatabaseAlone(): void
    {
        (new ManualDatabaseProvisioner())->drop('m1_acme', 'm1_acme');

        $this->assertTrue(true);
    }
}
