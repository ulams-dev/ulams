<?php

namespace EscolaLms\Translations\Tests\Middleware;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Translations\Database\Seeders\TranslationsPermissionSeeder;
use EscolaLms\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AcceptLanguageTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TranslationsPermissionSeeder::class);
        $this->admin = $this->makeAdmin();
    }

    public function testLocale(): void
    {
        $this->withMiddleware();
        $this->actingAs($this->admin, 'api')->getJson('api/admin/translations', [
            'X-Locale' => 'pl',
        ]);

        $this->assertEquals('pl', $this->app->getLocale());
    }
}