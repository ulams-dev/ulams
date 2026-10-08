<?php

namespace EscolaLms\Translations\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Translations\Database\Seeders\TranslationsPermissionSeeder;
use EscolaLms\Translations\Models\LanguageLine;
use EscolaLms\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LanguageLineDeleteApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private LanguageLine $languageLine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TranslationsPermissionSeeder::class);
        $this->admin = $this->makeAdmin();

        $this->languageLine = LanguageLine::create([
            'group' => 'validation',
            'key' => 'attributes.last_name',
            'text' => [
                'en' => 'last name',
                'pl' => 'nazwisko',
            ],
        ]);
    }

    public function testLanguageLineDeleteUnauthorized(): void
    {
        $this->deleteJson('api/admin/translations/' . $this->languageLine->getKey())
            ->assertUnauthorized();
    }

    public function testLanguageLineDelete(): void
    {
        $this->actingAs($this->admin, 'api')
            ->deleteJson('api/admin/translations/' . $this->languageLine->getKey())
            ->assertOk()
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseMissing('language_lines', [
            'id' => $this->languageLine->getKey(),
        ]);
    }

    public function testLanguageLineNotFound(): void
    {
        $this->languageLine->delete();

        $this->actingAs($this->admin, 'api')
            ->deleteJson('api/admin/translations/' . $this->languageLine->getKey())
            ->assertNotFound();
    }
}
