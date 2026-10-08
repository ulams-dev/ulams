<?php

namespace Ulams\Dictionaries\Tests\Api\DictionaryWord;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use Ulams\Dictionaries\Models\Category;
use Ulams\Dictionaries\Models\Dictionary;
use Ulams\Dictionaries\Models\DictionaryWord;
use Ulams\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminReadDictionaryWordApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminReadDictionaryWordUnauthorized(): void
    {
        $this->getJson('api/admin/dictionary-words/123')
            ->assertUnauthorized();
    }

    public function testAdminReadDictionaryWordForbidden(): void
    {
        $dictionaryWord = DictionaryWord::factory()->create();

        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/admin/dictionary-words/' . $dictionaryWord->getKey())
            ->assertForbidden();
    }

    public function testAdminReadDictionaryWordNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words/123')
            ->assertNotFound();
    }

    public function testAdminReadDictionaryWord(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];

        $dictionary = Dictionary::factory()->create();
        $dictionaryWord = DictionaryWord::factory()
            ->state(['data' => $data])
            ->hasAttached(Category::factory())
            ->for($dictionary)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words/' . $dictionaryWord->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonFragment([
                'id' => $dictionaryWord->getKey(),
                'word' => $dictionaryWord->word,
                'dictionary_id' => $dictionary->getKey(),
                'description' => $dictionaryWord->description,
                'data' => $dictionaryWord->data,
                'categories' => [[
                    'id' => $dictionaryWord->categories()->first()->getKey(),
                    'name' => $dictionaryWord->categories()->first()->name,
                    'name_with_breadcrumbs' => $dictionaryWord->categories()->first()->name_with_breadcrumbs,
                ]],
                'created_at' => $dictionaryWord->created_at,
                'updated_at' => $dictionaryWord->updated_at,
            ]);
    }
}
