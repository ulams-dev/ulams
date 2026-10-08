<?php

namespace EscolaLms\Dictionaries\Tests\Api\Dictionary;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminUpdateDictionaryApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminUpdateDictionaryUnauthorized(): void
    {
        $this->putJson('api/admin/dictionaries/123')
            ->assertUnauthorized();
    }

    public function testAdminUpdateDictionaryForbidden(): void
    {
        $dictionary = Dictionary::factory()->create();

        $this->actingAs($this->makeStudent(), 'api')
            ->putJson('api/admin/dictionaries/' . $dictionary->getKey(), [])
            ->assertForbidden();
    }

    public function testAdminUpdateDictionaryValidation(): void
    {
        $dictionary = Dictionary::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/dictionaries/' . $dictionary->getKey(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function testAdminUpdateDictionaryNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/dictionaries/123')
            ->assertNotFound();
    }

    public function testAdminUpdateDictionary(): void
    {
        $dictionary = Dictionary::factory()->create();
        $dictionaryData = Dictionary::factory()->make();

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/dictionaries/' . $dictionary->getKey(), $dictionaryData->toArray())
            ->assertOk()
            ->assertJsonFragment([
                'id' => $dictionary->getKey(),
                'name' => $dictionaryData->name,
                'slug' => $dictionary->slug,
                'free_views_count' => $dictionaryData->free_views_count,
                'created_at' => $dictionary->created_at,
            ]);
    }
}
