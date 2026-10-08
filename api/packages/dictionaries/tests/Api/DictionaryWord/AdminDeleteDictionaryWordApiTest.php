<?php

namespace EscolaLms\Dictionaries\Tests\Api\DictionaryWord;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use EscolaLms\Dictionaries\Tests\TestCase;
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
