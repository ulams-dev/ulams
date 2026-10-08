<?php

namespace Ulams\Dictionaries\Tests\Api\Dictionary;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use Ulams\Dictionaries\Models\Dictionary;
use Ulams\Dictionaries\Models\DictionaryWord;
use Ulams\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminDeleteDictionaryApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminDeleteDictionaryUnauthorized(): void
    {
        $this->deleteJson('api/admin/dictionaries/123')
            ->assertUnauthorized();
    }

    public function testAdminDeleteDictionaryNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('api/admin/dictionaries/123')
            ->assertNotFound();
    }

    public function testAdminDeleteDictionary(): void
    {
        /** @var DictionaryWord $dictionary */
        $dictionary = Dictionary::factory()
            ->has(DictionaryWord::factory()->count(10))
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('api/admin/dictionaries/' . $dictionary->getKey())
            ->assertOk();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionaries/' . $dictionary->getKey())
            ->assertNotFound();
    }
}
