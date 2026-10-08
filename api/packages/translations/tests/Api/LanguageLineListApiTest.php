<?php

namespace EscolaLms\Translations\Tests\Api;

use Carbon\Carbon;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Translations\Database\Seeders\TranslationsPermissionSeeder;
use EscolaLms\Translations\Models\LanguageLine;
use EscolaLms\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LanguageLineListApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private LanguageLine $authLanguageLine;
    private LanguageLine $validationLanguageLine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TranslationsPermissionSeeder::class);
        $this->admin = $this->makeAdmin();

        $this->authLanguageLine = LanguageLine::create([
            'group' => 'auth',
            'key' => 'failed',
            'text' => ['en' => 'These credentials do not match our records', 'pl' => 'Błędny login lub hasło'],
            'public' => false,
        ]);

        $this->validationLanguageLine = LanguageLine::create([
            'group' => 'validation',
            'key' => 'attributes.last_name',
            'text' => ['en' => 'last name', 'pl' => 'nazwisko'],
            'public' => false,
        ]);
    }

    public function testLanguageLineListUnauthorized(): void
    {
        $this->getJson('api/admin/translations')->assertUnauthorized();
    }

    public function testLanguageLineList(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('api/admin/translations')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->authLanguageLine->getKey(),
            ])
            ->assertJsonFragment([
                'id' => $this->validationLanguageLine->getKey(),
            ]);
    }

    public function testLanguageLineListReadonly(): void
    {
        $tutor = $this->makeInstructor();
        $this->actingAs($tutor, 'api')->json('GET', 'api/admin/translations')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->authLanguageLine->getKey(),
            ])
            ->assertJsonFragment([
                'id' => $this->validationLanguageLine->getKey(),
            ])
            ->assertJsonFragment([
                'current_page' => 1,
            ]);
    }

    public function testLanguageLineListAll(): void
    {
        $tutor = $this->makeInstructor();
        $this->actingAs($tutor, 'api')->json('GET', 'api/admin/translations', ['per_page' => -1])
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->authLanguageLine->getKey(),
            ])
            ->assertJsonFragment([
                'id' => $this->validationLanguageLine->getKey(),
            ])
            ->assertJsonMissing([
                'current_page' => 1,
            ]);
    }

    public function testLanguageLineListFilter(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('api/admin/translations?group=' . $this->validationLanguageLine->group)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->validationLanguageLine->getKey(),
            ])
            ->assertJsonMissing([
                'id' => $this->authLanguageLine->getKey(),
            ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('api/admin/translations?key=' . $this->authLanguageLine->key)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->authLanguageLine->getKey(),
            ])
            ->assertJsonMissing([
                'id' => $this->validationLanguageLine->getKey(),
            ]);
    }

    public function testLanguageLineListFilterByPublicField(): void
    {
        LanguageLine::factory()->count(5)->create(['public' => true]);
        LanguageLine::factory()->count(8)->create(['public' => false]);

        $this->actingAs($this->admin, 'api')
            ->getJson('api/admin/translations?public=1')
            ->assertJsonCount(5, 'data')
            ->assertOk();

        $this->actingAs($this->admin, 'api')
            ->getJson('api/admin/translations?public=0')
            ->assertJsonCount(10, 'data')
            ->assertOk();
    }

    public function testLanguageLineListOrderByPublicField(): void
    {
        $newest = LanguageLine::factory()->create(['created_at' => Carbon::now()->subDay()]);
        $oldest = LanguageLine::factory()->create(['created_at' => Carbon::now()->addDay()]);
        LanguageLine::factory()->count(5)->create();

        $this->response = $this->actingAs($this->admin, 'api')
            ->getJson('api/admin/translations?order_by=created_at&order=desc')
            ->assertOk();

        $this->assertEquals($oldest->id, $this->response->getData()->data[0]->id);

        $this->response = $this->actingAs($this->admin, 'api')
            ->getJson('api/admin/translations?order_by=created_at&order=asc')
            ->assertOk();

        $this->assertEquals($newest->id, $this->response->getData()->data[0]->id);
    }
}
