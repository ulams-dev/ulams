<?php

namespace EscolaLms\Cmi5\Tests\Api;

use EscolaLms\Cmi5\Database\Seeders\Cmi5PermissionSeeder;
use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Cmi5\Models\Cmi5Au;
use EscolaLms\Cmi5\Tests\TestCase;
use EscolaLms\Cmi5\Tests\Traits\Cmi5Testing;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class Cmi5ApiIndexTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, Cmi5Testing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Cmi5PermissionSeeder::class);
    }

    public function testIndexCmi5(): void
    {
        Cmi5::factory()
            ->has(Cmi5Au::factory()->count(2), 'aus')
            ->count(5)
            ->create();

        $admin = $this->makeAdmin();
        $response = $this
            ->actingAs($admin, 'api')
            ->json('GET','/api/admin/cmi5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonCount(2, 'data.0.au');

        $this->assertJsonStructure($response);
    }

    public function testPaginationIndexCmi5(): void
    {
        Cmi5::factory()
            ->has(Cmi5Au::factory(), 'aus')
            ->count(25)
            ->create();

        $admin = $this->makeAdmin();

        $response = $this
            ->actingAs($admin, 'api')
            ->json('GET','/api/admin/cmi5?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $this->assertJsonStructure($response);

        $response = $this
            ->actingAs($admin, 'api')
            ->json('GET','/api/admin/cmi5?page=3&per_page=10');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $this->assertJsonStructure($response);
    }
}
