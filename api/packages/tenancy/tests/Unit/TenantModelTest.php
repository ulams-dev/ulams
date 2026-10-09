<?php

namespace Ulams\Tenancy\Tests\Unit;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;

class TenantModelTest extends TestCase
{
    public function testSecretsAreEncryptedAtRest(): void
    {
        $tenant = Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes('crypt'), [
            'db_password' => 'plain-db-password',
            'passport_private_key' => 'PRIVATE KEY',
        ]));

        $row = DB::table('tenants')->where('id', $tenant->id)->first();
        $this->assertNotSame('plain-db-password', $row->db_password);
        $this->assertSame('plain-db-password', Crypt::decryptString($row->db_password));
        $this->assertStringNotContainsString('base64:', $row->app_key);
        $this->assertNotSame('PRIVATE KEY', $row->passport_private_key);

        $fresh = Tenant::query()->find($tenant->id);
        $this->assertSame('plain-db-password', $fresh->db_password);
        $this->assertSame('PRIVATE KEY', $fresh->passport_private_key);
    }

    public function testSecretsAreHiddenFromSerialisation(): void
    {
        $tenant = new Tenant(TenantNaming::newTenantAttributes('hidden'));

        $array = $tenant->toArray();
        foreach (['db_password', 'app_key', 'passport_private_key', 'passport_public_key'] as $secret) {
            $this->assertArrayNotHasKey($secret, $array);
        }
        $this->assertSame('hidden.localhost', $array['api_host']);
    }

    public function testStepsAreRecorded(): void
    {
        $tenant = Tenant::query()->create(TenantNaming::newTenantAttributes('steps'));

        $tenant->markCompleted('database');
        $this->assertTrue($tenant->fresh()->hasCompleted('database'));
        $this->assertFalse($tenant->fresh()->hasCompleted('bucket'));

        $tenant->forget('database');
        $this->assertFalse($tenant->hasCompleted('database'));
    }
}
