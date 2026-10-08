<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryWord;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminUpdateDictionaryWordApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminUpdateDictionaryWordUnauthorized(): void
    {
        $this->putJson('api/admin/dictionary-words/123')
            ->assertUnauthorized();
    }

    public function testAdminUpdateDictionaryWordForbidden(): void
    {
        $dictionaryWord = DictionaryWord::factory()->create();

        $this->actingAs($this->makeStudent(), 'api')
            ->putJson('api/admin/dictionary-words/' . $dictionaryWord->getKey(), [])
            ->assertForbidden();
    }

    public function testAdminUpdateDictionaryWordValidation(): void
    {
        $dictionaryWord = DictionaryWord::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/dictionary-words/' . $dictionaryWord->getKey(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['word', 'dictionary_id']);
    }

    public function testAdminUpdateDictionaryWordNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/dictionary-words/123')
            ->assertNotFound();
    }

    public function testAdminUpdateDictionaryWord(): void
    {
        $dictionaryWord = DictionaryWord::factory()->create();

        $data = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];

        $category = Category::factory()->create();
        $dictionaryWordData = DictionaryWord::factory()->state([
            'dictionary_id' => $dictionaryWord->dictionary_id,
            'data' => $data,
            'categories' => [$category->getKey()],
        ])
            ->make();

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/dictionary-words/' . $dictionaryWord->getKey(), $dictionaryWordData->toArray())
            ->assertOk()
            ->assertJsonFragment([
                'id' => $dictionaryWord->getKey(),
                'word' => $dictionaryWordData->word,
                'dictionary_id' => $dictionaryWord->dictionary_id,
                'description' => $dictionaryWordData->description,
                'data' => $dictionaryWordData->data,
                'categories' => [[
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'name_with_breadcrumbs' => $category->name_with_breadcrumbs,
                ]],
                'created_at' => $dictionaryWord->created_at,
            ]);
    }
}
