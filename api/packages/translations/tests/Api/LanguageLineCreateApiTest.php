<?php

namespace Ulams\Translations\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Translations\Models\LanguageLine;
use Ulams\Translations\Database\Seeders\TranslationsPermissionSeeder;
use Ulams\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LanguageLineCreateApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TranslationsPermissionSeeder::class);
        $this->admin = $this->makeAdmin();
    }

    public function testLanguageLineCreateUnauthorized(): void
    {
        $this->postJson('api/admin/translations')->assertUnauthorized();
    }

    public function testLanguageLineCreateRequiredValidation(): void
    {
        $response = $this->actingAs($this->admin, 'api')->postJson('api/admin/translations');
        $response->assertJsonValidationErrors(['group', 'key', 'text']);
    }

    public function testLanguageLineCreateGroupAndKeyMustBeUnique(): void
    {
        $languageLine = LanguageLine::create([
            'group' => 'validation',
            'key' => 'attributes.last_name',
            'text' => [
                'en' => 'last name',
                'pl' => 'nazwisko',
            ],
        ]);

        $this->response = $this->actingAs($this->admin, 'api')
            ->postJson('api/admin/translations', $languageLine->toArray())
            ->assertJsonValidationErrors(['key']);

    }

    public function testLanguageLineCreate(): void
    {
        $data = [
            'group' => 'validation',
            'key' => 'attributes.last_name',
            'text' => [
                'en' => 'last name',
                'pl' => 'nazwisko',
            ],
        ];

        $this->response = $this->actingAs($this->admin, 'api')
            ->postJson('api/admin/translations', $data)
            ->assertCreated();

        $this->assertApiResponse($data);
    }
}
