<?php

namespace EscolaLms\Dictionaries\Tests\Api\Dictionary;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminReadDictionaryApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminReadDictionaryUnauthorized(): void
    {
        $this->getJson('api/admin/dictionaries/123')
            ->assertUnauthorized();
    }

    public function testAdminReadDictionaryForbidden(): void
    {
        $dictionary = Dictionary::factory()->create();

        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/admin/dictionaries/' . $dictionary->getKey())
            ->assertForbidden();
    }

    public function testAdminReadDictionaryNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries/123')
            ->assertNotFound();
    }

    public function testAdminReadDictionary(): void
    {
        $dictionary = Dictionary::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries/' . $dictionary->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'id' => $dictionary->getKey(),
                'name' => $dictionary->name,
                'slug' => $dictionary->slug,
                'free_views_count' => $dictionary->free_views_count,
                'created_at' => $dictionary->created_at,
                'updated_at' => $dictionary->updated_at,
            ]);
    }
}
