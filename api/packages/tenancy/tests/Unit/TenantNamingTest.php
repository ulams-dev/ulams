<?php

namespace Ulams\Tenancy\Tests\Unit;

use InvalidArgumentException;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TenantNamingTest extends TestCase
{
    public function testDerivesResourceNamesFromTheSlug(): void
    {
        $attributes = TenantNaming::newTenantAttributes('nightsky');

        $this->assertSame('nightsky.localhost', $attributes['api_host']);
        $this->assertSame('nightsky.app.localhost', $attributes['front_host']);
        $this->assertSame('nightsky.admin.localhost', $attributes['admin_host']);
        $this->assertSame('ulams_nightsky', $attributes['db_name']);
        $this->assertSame('ulams-nightsky', $attributes['bucket']);
        $this->assertSame('ulams_nightsky_', $attributes['redis_prefix']);
        $this->assertSame(Tenant::STATUS_PROVISIONING, $attributes['status']);
    }

    public function testEveryTenantGetsItsOwnSecrets(): void
    {
        $a = TenantNaming::newTenantAttributes('alpha');
        $b = TenantNaming::newTenantAttributes('bravo');

        $this->assertNotSame($a['db_password'], $b['db_password']);
        $this->assertNotSame($a['app_key'], $b['app_key']);
        $this->assertSame(32, strlen(base64_decode(substr($a['app_key'], 7))));
    }

    public function testEnvValuesIsolateRedisDatabaseAndStorage(): void
    {
        $values = TenantNaming::envValues(new Tenant(array_merge(
            TenantNaming::newTenantAttributes('coffee'),
            ['name' => 'The Coffee Atlas']
        )));

        $this->assertSame('ulams_coffee_', $values['REDIS_PREFIX']);
        $this->assertSame('ulams_coffee_cache', $values['CACHE_PREFIX']);
        $this->assertSame('ulams_coffee_horizon:', $values['HORIZON_PREFIX']);
        $this->assertSame('http://storage.localhost/ulams-coffee', $values['AWS_URL']);
        $this->assertSame('http://coffee.app.localhost', $values['FRONTEND_URL']);
        $this->assertSame('admin@coffee.ulams.app', $values['INITIAL_USER_EMAIL']);
        $this->assertSame('no-reply@coffee.ulams.app', $values['MAIL_FROM_ADDRESS']);
    }

    #[DataProvider('invalidSlugs')]
    public function testRejectsInvalidSlugs(string $slug): void
    {
        $this->expectException(InvalidArgumentException::class);
        TenantNaming::assertValidSlug($slug);
    }

    public static function invalidSlugs(): array
    {
        return [['a'], ['Coffee'], ['1abc'], ['with-dash'], ['under_score'], ['a.b'], [str_repeat('a', 31)], ['admin'], ['api']];
    }

    public function testQuotesEnvValuesOnlyWhenNeeded(): void
    {
        $this->assertSame('ulams_coffee_horizon:', TenantNaming::envValue('ulams_coffee_horizon:'));
        $this->assertSame('base64:abc+/=', TenantNaming::envValue('base64:abc+/='));
        $this->assertSame('"The Coffee Atlas"', TenantNaming::envValue('The Coffee Atlas'));
        $this->assertSame('"a \\"b\\" \\$c"', TenantNaming::envValue('a "b" $c'));
    }
}
