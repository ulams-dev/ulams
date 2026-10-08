<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryWord;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class ListDictionaryWordApiTest extends TestCase
{
    public function testListDictionaryWords(): void
    {
        $dictionary = Dictionary::factory()->create();

        DictionaryWord::factory()
            ->for($dictionary)
            ->count(4)
            ->has(Category::factory())
            ->create();

        $this
            ->getJson('/api/dictionaries/1234/words')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this
            ->getJson('/api/dictionaries/' . $dictionary->slug . '/words')
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

    public function testListDictionaryWordsPagination(): void
    {
        DictionaryWord::factory()->count(40)->create();

        $dictionary = Dictionary::factory()->create();
        DictionaryWord::factory()
            ->for($dictionary)
            ->count(35)
            ->create();

        $this->getJson('/api/dictionaries/' . $dictionary->slug . '/words?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35,
                ],
            ]);

        $this->getJson('/api/dictionaries/' . $dictionary->slug . '/words?per_page=10&page=4')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35,
                ],
            ]);
    }

    public function testListDictionaryWordsFiltering(): void
    {
        $dictionary = Dictionary::factory()->create();

        DictionaryWord::factory()
            ->count(4)
            ->sequence(
                ['dictionary_id' => $dictionary->getKey(), 'word' => 'Test1'],
                ['dictionary_id' => $dictionary->getKey(), 'word' => 'test12'],
                ['dictionary_id' => $dictionary->getKey(), 'word' => 'Test3'],
                ['dictionary_id' => $dictionary->getKey(), 'word' => 'test321'],
            )
            ->create();

        $this->getJson('/api/dictionaries/' . $dictionary->slug . '/words?word=test')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->getJson('/api/dictionaries/' . $dictionary->slug . '/words?word=st1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/dictionaries/' . $dictionary->slug . '/words?word_start=st1')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/dictionaries/' . $dictionary->slug . '/words?word_start=test3')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
