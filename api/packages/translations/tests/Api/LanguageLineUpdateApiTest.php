<?php

namespace EscolaLms\Translations\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Translations\Database\Seeders\TranslationsPermissionSeeder;
use EscolaLms\Translations\Models\LanguageLine;
use EscolaLms\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LanguageLineUpdateApiTest extends TestCase
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

    public function testLanguageLineUpdateUnauthorized(): void
    {
        $this->putJson('api/admin/translations/' . $this->languageLine->getKey())
            ->assertUnauthorized();
    }

    public function testLanguageLineUpdate(): void
    {
        $data = [
            'text' => [
                'en' => 'LAST NAME',
                'pl' => 'NAZWISKO',
            ],
        ];

        $this->response = $this->actingAs($this->admin, 'api')
            ->putJson('api/admin/translations/' . $this->languageLine->getKey(), $data
            )->assertOk();

        $this->assertApiResponse(array_merge($this->languageLine->toArray(), $data));
    }
}
