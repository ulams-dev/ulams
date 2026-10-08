<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryWord;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class ReadDictionaryWordApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testReadDictionaryWordNotFound(): void
    {
        $this->getJson('api/admin/dictionary/123/words')
            ->assertNotFound();
    }

    public function testReadDictionaryWord(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $dictionary = Dictionary::factory()->state(['free_views_count' => null])->create();
        $dictionaryWord = DictionaryWord::factory()
            ->state(['data' => $data])
            ->hasAttached(Category::factory())
            ->for($dictionary)
            ->create();

        $this->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
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

    public function testReadDictionaryWordWhenUserIsAssignedToDictionary(): void
    {
        $dictionary = Dictionary::factory()->state(['free_views_count' => 1])->create();
        $dictionaryWord = DictionaryWord::factory()->for($dictionary)->create();
        $user = $this->makeStudent();
        $dictionary->users()->attach($user);

        $this->actingAs($user, 'api')
            ->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
            ->assertOk();

        $this->actingAs($user, 'api')
            ->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
            ->assertOk();
    }

    public function testReadDictionaryWordWhenUserIsAuthenticatedButNotAssignedToDictionary(): void
    {
        $freeViewsCount = $this->faker->numberBetween(2, 10);
        $dictionary = Dictionary::factory()->state(['free_views_count' => $freeViewsCount])->create();
        $dictionaryWord = DictionaryWord::factory()->for($dictionary)->create();
        $user = $this->makeStudent();

        // free
        for ($i = 0; $i < $freeViewsCount; $i++) {
            $this->actingAs($user, 'api')
                ->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
                ->assertOk();
        }

        // the next is to exceed the number of attempts
        $this->actingAs($user, 'api')
            ->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
            ->assertTooManyRequests();

        // go to the next day
        $this->travelTo(now()->addHours(24)->addMinute());
        $this->actingAs($user, 'api')
            ->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
            ->assertOk();
    }

    public function testReadDictionaryWordWhenUserIsNotAuthenticated(): void
    {
        $freeViewsCount = $this->faker->numberBetween(2, 10);
        $dictionary = Dictionary::factory()->state(['free_views_count' => $freeViewsCount])->create();
        $dictionaryWord = DictionaryWord::factory()->for($dictionary)->create();

        // free
        for ($i = 0; $i < $freeViewsCount; $i++) {
            $this->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
                ->assertOk();
        }

        // the next is to exceed the number of attempts
        $this->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
            ->assertTooManyRequests();

        // go to the next day
        $this->travelTo(now()->addHours(24)->addMinute());
        $this->getJson('api/dictionaries/' . $dictionaryWord->getKey() . '/words/' . $dictionaryWord->getKey())
            ->assertOk();
    }
}
