<?php

namespace EscolaLms\Dictionaries\Tests\Api\Dictionary;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminListDictionaryApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminListDictionariesUnauthorized(): void
    {
        $this->getJson('api/admin/dictionaries')
            ->assertUnauthorized();
    }

    public function testAdminListDictionaries(): void
    {
        Dictionary::factory()
            ->count(4)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries')
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'slug',
                    'free_views_count',
                    'created_at',
                    'updated_at',
                ]],
            ]);
    }

    public function testAdminListDictionariesFiltering(): void
    {
        Dictionary::factory()
            ->count(4)
            ->sequence(
                ['name' => 'test1', 'slug' => 'slug1'],
                ['name' => 'test12', 'slug' => 'slug-1'],
                ['name' => 'test3', 'slug' => 'test3'],
                ['name' => 'test321', 'slug' => 'test321'],
            )
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries?name=test')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries?name=test1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries?slug=slug')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function testAdminListDictionariesPagination(): void
    {
        Dictionary::factory()
            ->count(35)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35
                ]
            ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries?per_page=10&page=4')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35
                ],
            ]);
    }
}
