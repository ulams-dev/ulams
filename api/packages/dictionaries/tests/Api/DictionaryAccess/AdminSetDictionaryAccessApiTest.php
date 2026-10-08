<?php

namespace Ulams\Dictionaries\Tests\Api\DictionaryAccess;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use Ulams\Dictionaries\Models\Dictionary;
use Ulams\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminSetDictionaryAccessApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminSetDictionaryAccessUnauthorized(): void
    {
        $this->postJson('api/admin/dictionaries/123/access')
            ->assertUnauthorized();
    }

    public function testAdminSetDictionaryAccessForbidden(): void
    {
        $dictionary = Dictionary::factory()->create();

        $this
            ->actingAs($this->makeStudent(), 'api')
            ->postJson('api/admin/dictionaries/' . $dictionary->getKey() . '/access')
            ->assertForbidden();
    }

    public function testAdminSetDictionaryAccess(): void
    {
        $dictionary = Dictionary::factory()->create();
        $user = $this->makeStudent();

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/dictionaries/' . $dictionary->getKey() . '/access', [
                'users' => [[
                    'user_id' => $user->getKey(),
                    'end_date' => null,
                ]],
            ])
            ->assertOk();

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries/' . $dictionary->getKey() . '/access')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'end_date' => null,
                'is_active' => true,
            ]);
    }
}
