<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryWord;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminCreateDictionaryWordApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminCreateDictionaryWordUnauthorized(): void
    {
        $this->postJson('api/admin/dictionary-words')
            ->assertUnauthorized();
    }

    public function testAdminCreateDictionaryWordForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/admin/dictionary-words')
            ->assertForbidden();
    }

    public function testAdminCreateDictionaryWordValidation(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionary-words', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['word', 'dictionary_id']);
    }

    public function testAdminCreateDictionaryWord(): void
    {
        $dictionaryWordData = DictionaryWord::factory()->make();
        $categories = Category::factory()->count(3)->create()->pluck('id');

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionary-words', [
                ...$dictionaryWordData->toArray(),
                'categories' => $categories,
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'word',
                    'dictionary_id',
                    'description',
                    'data',
                    'categories' => [[
                        'id',
                        'name',
                        'name_with_breadcrumbs',
                    ]],
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonFragment([
                'word' => $dictionaryWordData->word,
                'dictionary_id' => $dictionaryWordData->dictionary_id,
                'description' => $dictionaryWordData->description,
                'data' => $dictionaryWordData->data,
            ])
            ->assertJsonCount(3, 'data.categories');
    }

    public function testAdminCreateDictionaryWordWithNullDescription(): void
    {
        $dictionaryWordData = DictionaryWord::factory()->make();
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionary-words', [
                ...$dictionaryWordData->toArray(),
               'description' => null,
            ])
            ->assertCreated()
            ->assertJsonFragment([
                'word' => $dictionaryWordData->word,
                'dictionary_id' => $dictionaryWordData->dictionary_id,
                'description' => null,
                'data' => $dictionaryWordData->data,
            ]);
    }
}
