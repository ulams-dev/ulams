<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryWord;

use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use EscolaLms\Dictionaries\Tests\TestCase;

class ListDictionaryWordCategoryApiTest extends TestCase
{
    public function testListDictionaryWordCategories(): void
    {
        Category::factory()->count(10)->create();
        DictionaryWord::factory()->count(10)->hasAttached(Category::factory()->count(2))->create();

        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();
        $dictionary1 = Dictionary::factory()->create();
        $word1 = DictionaryWord::factory()->for($dictionary1)->hasAttached($category1)->create();
        DictionaryWord::factory()->for($dictionary1)->hasAttached($category1)->create();
        DictionaryWord::factory()->for($dictionary1)->hasAttached($category2)->create();

        $this->getJson('api/dictionaries/123/words/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('api/dictionaries/' . $dictionary1->slug . '/words/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'data' => [
                    [
                        'id' => $category1->getKey(),
                        'name' => $category1->name,
                        'name_with_breadcrumbs' => $category1->name_with_breadcrumbs,
                        'dictionary_words_count' => 2,
                    ],
                    [
                        'id' => $category2->getKey(),
                        'name' => $category2->name,
                        'name_with_breadcrumbs' => $category2->name_with_breadcrumbs,
                        'dictionary_words_count' => 1,
                    ],
                ],
            ]);

        $this->getJson('api/dictionaries/' . $dictionary1->slug . '/words/categories?word=' . $word1->word)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'data' => [[
                    'id' => $category1->getKey(),
                    'name' => $category1->name,
                    'name_with_breadcrumbs' => $category1->name_with_breadcrumbs,
                    'dictionary_words_count' => 1,
                ]],
            ]);
    }
}
