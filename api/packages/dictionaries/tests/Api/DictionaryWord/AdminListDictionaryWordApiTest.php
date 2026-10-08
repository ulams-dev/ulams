<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryWord;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminListDictionaryWordApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminListDictionaryWordsUnauthorized(): void
    {
        $this->getJson('api/admin/dictionary-words')
            ->assertUnauthorized();
    }

    public function testAdminListDictionaryWords(): void
    {
        DictionaryWord::factory()
            ->count(4)
            ->has(Category::factory())
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words')
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'word',
                    'dictionary_id',
                    'categories' => [[
                        'id',
                        'name',
                        'name_with_breadcrumbs',
                    ]],
                    'created_at',
                    'updated_at',
                ]],
            ]);
    }

    public function testAdminListDictionaryWordsFiltering(): void
    {
        $dictionary1 = Dictionary::factory()->create();
        $dictionary2 = Dictionary::factory()->create();

        DictionaryWord::factory()
            ->count(4)
            ->sequence(
                ['dictionary_id' => $dictionary1->getKey(), 'word' => 'test1'],
                ['dictionary_id' => $dictionary1->getKey(), 'word' => 'test12'],
                ['dictionary_id' => $dictionary1->getKey(), 'word' => 'test3'],
                ['dictionary_id' => $dictionary2->getKey(), 'word' => 'test321'],
            )
            ->create();

        /** @var Category $category1 */
        $category1 = Category::factory()->create();

        DictionaryWord::factory()
            ->count(5)
            ->hasAttached($category1)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words?dictionary_id=' . $dictionary1->getKey())
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words?word=test1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words?category_ids[]=' . $category1->getKey())
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function testAdminListDictionaryWordsPagination(): void
    {
        DictionaryWord::factory()
            ->count(35)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35,
                ],
            ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words?per_page=10&page=4')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35,
                ],
            ]);
    }
}
