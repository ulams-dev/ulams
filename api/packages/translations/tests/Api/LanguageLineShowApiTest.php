<?php

namespace EscolaLms\Translations\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Translations\Database\Seeders\TranslationsPermissionSeeder;
use EscolaLms\Translations\Models\LanguageLine;
use EscolaLms\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LanguageLineShowApiTest extends TestCase
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

    public function testLanguageLineShowUnauthorized(): void
    {
        $this->getJson('api/admin/translations/' . $this->languageLine->getKey())
            ->assertUnauthorized();
    }

    public function testLanguageLineShow(): void
    {
        $this->response = $this->actingAs($this->admin, 'api')
            ->getJson('api/admin/translations/' . $this->languageLine->getKey())
            ->assertOk();

        $this->assertApiResponse($this->languageLine->toArray());
    }
}
