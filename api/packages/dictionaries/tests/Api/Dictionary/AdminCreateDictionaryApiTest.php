<?php

namespace EscolaLms\Dictionaries\Tests\Api\Dictionary;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;

class AdminCreateDictionaryApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminCreateDictionaryUnauthorized(): void
    {
        $this->postJson('api/admin/dictionaries')
            ->assertUnauthorized();
    }

    public function testAdminCreateDictionaryForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/admin/dictionaries')
            ->assertForbidden();
    }

    public function testAdminCreateDictionaryValidation(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionaries', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function testAdminCreateDictionary(): void
    {
        $dictionaryData = Dictionary::factory()->make();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionaries', $dictionaryData->toArray())
            ->assertCreated()
            ->assertJsonFragment([
                'name' => $dictionaryData->name,
                'slug' => Str::slug($dictionaryData->name),
                'free_views_count' => $dictionaryData->free_views_count,
            ]);
    }

    public function testAdminCreateDictionaryWithUniqueSlug(): void
    {
        Dictionary::factory()->state(['slug' => 'test'])->create();

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionaries', [
                'name' => 'test',
            ])
            ->assertCreated()
            ->assertJsonFragment([
                'name' => 'test',
                'free_views_count' => null,
            ]);

        $slug = $response->getData()->data->slug;
        $this->assertNotEquals('test', $slug);
        $this->assertStringStartsWith('test', $slug);
    }
}
