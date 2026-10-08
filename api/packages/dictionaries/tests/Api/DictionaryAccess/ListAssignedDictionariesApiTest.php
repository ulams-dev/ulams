<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryAccess;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;

class ListAssignedDictionariesApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testListAssignedDictionariesUnauthorized(): void
    {
        $this->getJson('api/dictionaries/access')
            ->assertUnauthorized();
    }

    public function testListAssignedDictionaries(): void
    {
        Dictionary::factory()->count(5)->create();
        $user = $this->makeStudent();
        $date1 = Carbon::now()->addMonth()->format('Y-m-d H:i:s');
        $date2 = Carbon::now()->subMonth()->format('Y-m-d H:i:s');

        $dictionary1 = Dictionary::factory()->create();
        $dictionary2 = Dictionary::factory()->create();
        $dictionary3 = Dictionary::factory()->create();

        $dictionary1->users()->attach($user->getKey());
        $dictionary2->users()->attach($user->getKey(), ['end_date' => $date1]);
        $dictionary3->users()->attach($user->getKey(), ['end_date' => $date2]);

        $this
            ->actingAs($user, 'api')
            ->getJson('api/dictionaries/access')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment([
                'data' => [
                    [
                        'dictionary_id' => $dictionary1->getKey(),
                        'name' => $dictionary1->name,
                        'slug' => $dictionary1->slug,
                        'end_date' => null,
                        'is_active' => true,
                    ],
                    [
                        'dictionary_id' => $dictionary2->getKey(),
                        'name' => $dictionary2->name,
                        'slug' => $dictionary2->slug,
                        'end_date' => $date1,
                        'is_active' => true,
                    ],
                    [
                        'dictionary_id' => $dictionary3->getKey(),
                        'name' => $dictionary3->name,
                        'slug' => $dictionary3->slug,
                        'end_date' => $date2,
                        'is_active' => false,
                    ],
                ],
            ]);
    }
}
