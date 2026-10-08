<?php

namespace Ulams\Translations\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Translations\Database\Seeders\TranslationsPermissionSeeder;
use Ulams\Translations\Models\LanguageLine;
use Ulams\Translations\Tests\TestCase;
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
