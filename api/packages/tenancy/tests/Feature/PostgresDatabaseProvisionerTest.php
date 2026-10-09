<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDO;
use Ulams\Tenancy\Services\PostgresDatabaseProvisioner;
use Ulams\Tenancy\Tests\TestCase;

/**
 * Talks to the real PostgreSQL server through the superuser connection.
 */
class PostgresDatabaseProvisionerTest extends TestCase
{
    private const DB = 'ulams_tenancy_probe';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Needs PostgreSQL.');
        }
        config(['database.connections.pgsql_admin' => config('database.connections.pgsql')]);
    }

    protected function tearDown(): void
    {
        $this->app->make(PostgresDatabaseProvisioner::class)->drop(self::DB, self::DB);
        DB::purge('pgsql_admin');
        parent::tearDown();
    }

    public function testCreatesRoleAndDatabaseIdempotentlyAndDropsThem(): void
    {
        $provisioner = $this->app->make(PostgresDatabaseProvisioner::class);
        $admin = DB::connection('pgsql_admin');

        $provisioner->ensure(self::DB, self::DB, 'first-password');
        $provisioner->ensure(self::DB, self::DB, "second-'password");

        $this->assertNotNull($admin->selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [self::DB]));
        $owner = $admin->selectOne(
            'SELECT pg_get_userbyid(datdba) AS owner FROM pg_database WHERE datname = ?',
            [self::DB]
        );
        $this->assertSame(self::DB, $owner->owner);

        // the role logs in with the latest password
        $config = config('database.connections.pgsql');
        $pdo = new PDO(
            "pgsql:host={$config['host']};port={$config['port']};dbname=" . self::DB,
            self::DB,
            "second-'password"
        );
        $this->assertSame(self::DB, $pdo->query('SELECT current_database()')->fetchColumn());
        $pdo = null;

        $provisioner->drop(self::DB, self::DB);
        $this->assertNull($admin->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [self::DB]));
        $this->assertNull($admin->selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [self::DB]));
    }

    public function testRejectsUnsafeIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(PostgresDatabaseProvisioner::class)->ensure('x"; DROP DATABASE default; --', 'x', 'p');
    }
}
