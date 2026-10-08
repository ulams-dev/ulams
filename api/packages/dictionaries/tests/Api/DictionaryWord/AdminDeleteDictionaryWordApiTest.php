<?php

namespace Ulams\Dictionaries\Tests\Api\DictionaryWord;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use Ulams\Dictionaries\Models\DictionaryWord;
use Ulams\Dictionaries\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class AdminDeleteDictionaryWordApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionariesPermissionSeeder::class);
    }

    public function testAdminDeleteDictionaryWordUnauthorized(): void
    {
        $this->deleteJson('api/admin/dictionary-words/123')
            ->assertUnauthorized();
    }

    public function testAdminDeleteDictionaryWordNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('api/admin/dictionary-words/123')
            ->assertNotFound();
    }

    public function testAdminDeleteDictionaryWord(): void
    {
        $dictionaryWord = DictionaryWord::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('api/admin/dictionary-words/' . $dictionaryWord->getKey())
            ->assertOk();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/dictionary-words/' . $dictionaryWord->getKey())
            ->assertNotFound();
    }
}
