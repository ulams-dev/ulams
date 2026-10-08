<?php

namespace EscolaLms\Translations\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Lang;
use Illuminate\Testing\Fluent\AssertableJson;

class RetrieveTranslationApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    public function testRetrieveTranslationUnauthorized(): void
    {
        $this->postJson('api/admin/translations/retrieve', [
            'key' => 'key',
        ])->assertUnauthorized();
    }

    public function testRetrieveTranslationByFullKey(): void
    {
        $admin = $this->makeAdmin();
        $key = 'translation::permissions.translation_list';

        $this->actingAs($admin, 'api')->postJson('api/admin/translations/retrieve', [
            'key' => $key,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'key' => $key,
                'value' => Lang::get($key),
            ]);
    }

    public function testRetrieveTranslationsByPartialKey(): void
    {
        $admin = $this->makeAdmin();
        $key = 'translation::permissions';

        $this->actingAs($admin, 'api')->postJson('api/admin/translations/retrieve', [
            'key' => $key,
        ])
            ->assertOk()
            ->assertJsonCount(count(Lang::get($key)), 'data')
            ->assertJson(fn(AssertableJson $json) => $json->has('data', fn(AssertableJson $json) =>
            $json->each(fn(AssertableJson $json) => $json->where('key', fn($key) => substr($key, 0, strlen($key)) === $key)
                ->etc())
            )->etc());
    }
}
