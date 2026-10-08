<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryWord;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;

class AdminImportDictionaryWordApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminImportDictionaryWordsUnauthorized(): void
    {
        $this->postJson('api/admin/dictionary-words/import')
            ->assertUnauthorized();
    }

    public function testAdminImportDictionaryWordsForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/admin/dictionary-words/import')
            ->assertForbidden();
    }

    public function testAdminImportDictionaryWordsValidation(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionary-words/import')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dictionary_id', 'file']);

        $dictionary = Dictionary::factory()->create();
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionary-words/import', [
                'dictionary_id' => $dictionary->getKey(),
                'file' => new UploadedFile(
                    __DIR__ . '/../../mocks/import_dictionary_words_example.xlsx',
                    'import_dictionary_words_example.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ])
            ->assertUnprocessable();
    }

    public function testAdminImportDictionaryWords(): void
    {
        Category::factory()
            ->count(2)
            ->sequence(
                ['name' => 'category1'],
                ['name' => 'category2'],
            )
            ->create();

        $dictionary = Dictionary::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionary-words/import', [
                'dictionary_id' => $dictionary->getKey(),
                'file' => new UploadedFile(
                    __DIR__ . '/../../mocks/import_dictionary_words_example.xlsx',
                    'import_dictionary_words_example.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ])
            ->assertOk();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words?dictionary_id=' . $dictionary->getKey())
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(0, 'data.0.categories')
            ->assertJsonCount(2, 'data.1.categories')
            ->assertJsonCount(1, 'data.2.categories');
    }
}
