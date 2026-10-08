<?php

namespace Ulams\Translations\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Translations\Database\Seeders\TranslationsPermissionSeeder;
use Ulams\Translations\Models\LanguageLine;
use Ulams\Translations\Tests\TestCase;
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
