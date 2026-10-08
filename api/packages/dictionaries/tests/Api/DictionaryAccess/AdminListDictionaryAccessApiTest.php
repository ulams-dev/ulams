<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryAccess;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Models\User;
use EscolaLms\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;

class AdminListDictionaryAccessApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminListDictionaryAccessUnauthorized(): void
    {
        $this->getJson('api/admin/dictionaries/123/access')
            ->assertUnauthorized();
    }

    public function testAdminListDictionaryAccessForbidden(): void
    {
        $dictionary = Dictionary::factory()->create();

        $this
            ->actingAs($this->makeStudent(), 'api')
            ->getJson('api/admin/dictionaries/' . $dictionary->getKey() . '/access')
            ->assertForbidden();
    }

    public function testAdminListDictionaryAccessNotFound(): void
    {
        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries/123/access')
            ->assertNotFound();
    }

    public function testAdminListDictionaryAccess(): void
    {
        Dictionary::factory()->count(5)->hasAttached(User::factory())->create();
        $dictionary = Dictionary::factory()->create();

        $user1 = $this->makeStudent();
        $user2 = $this->makeStudent();
        $date = Carbon::now()->subMonth()->format('Y-m-d H:i:s');

        $dictionary->users()->attach($user1->getKey(), ['end_date' => $date]);
        $dictionary->users()->attach($user2->getKey(), ['end_date' => null]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries/' . $dictionary->getKey() . '/access')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'data' => [
                    [
                        'user' => [
                            'id' => $user1->getKey(),
                            'name' => $user1->name,
                            'email' => $user1->email,
                        ],
                        'end_date' => $date,
                        'is_active' => false,
                    ],
                    [
                        'user' => [
                            'id' => $user2->getKey(),
                            'name' => $user2->name,
                            'email' => $user2->email,
                        ],
                        'end_date' => null,
                        'is_active' => true,
                    ],
                ],
            ]);

    }
}
