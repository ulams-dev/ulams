<?php

namespace EscolaLms\Translations\Tests\Api;

use EscolaLms\Translations\Models\LanguageLine;
use EscolaLms\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Testing\Fluent\AssertableJson;

class LanguageLinePublicListApiTest extends TestCase
{
    use DatabaseTransactions;

    private Collection $privateTranslations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privateTranslations = LanguageLine::factory()->count(5)->create(['public' => false]);
    }

    public function testGetOnlyPublicLanguageTranslationList(): void
    {
        $public = LanguageLine::factory()->count(10)->create(['public' => true]);

        $this->response = $this->getJson('api/translations');

        $this->assertListApiResponse($public);
    }

    public function testGetEmptyPublicLanguageTranslationList(): void
    {
        $this->getJson('api/translations')
            ->assertJsonCount(0, 'data')
            ->assertOk();
    }

    public function testShouldNotFilterByPublic(): void
    {
        $this->response = $this->getJson('api/translations?public=false')
            ->assertJsonCount(0, 'data')
            ->assertOk();
    }

    public function testFilterByGroupPublicLanguageTranslationList(): void
    {
        $data = LanguageLine::factory()->count(1)->create([
            'group' => 'group123',
            'public' => true
        ]);

        $this->response = $this->getJson('api/translations?group[]=group123');

        $this->assertListApiResponse($data, 1);
        $this->assertEquals($this->response->getData()->data[0]->id, $data[0]->id);
        $this->assertEquals($this->response->getData()->data[0]->group, $data[0]->group);
    }

    public function testFilterByKeyPublicLanguageTranslationList(): void
    {
        $data = LanguageLine::factory()->count(1)->create([
            'key' => 'key123',
            'public' => true
        ]);

        $this->response = $this->getJson('api/translations?key[]=key123');
        $this->assertListApiResponse($data, 1);
        $this->assertEquals($this->response->getData()->data[0]->id, $data[0]->id);
        $this->assertEquals($this->response->getData()->data[0]->key, $data[0]->key);
    }

    public function testFilterByMultipleKeyPublicLanguageTranslationList(): void
    {
        $data[] = LanguageLine::factory()->create([
            'key' => 'k1',
            'public' => true
        ]);
        $data[] = LanguageLine::factory()->create([
            'key' => 'k2',
            'public' => true
        ]);

        $this->response = $this->getJson('api/translations?key[]=k1&key[]=k2');
        $this->assertListApiResponse(collect($data), 2);
        $this->assertEquals($this->response->getData()->data[0]->id, $data[0]->id);
        $this->assertEquals($this->response->getData()->data[0]->key, $data[0]->key);
        $this->assertEquals($this->response->getData()->data[1]->id, $data[1]->id);
        $this->assertEquals($this->response->getData()->data[1]->key, $data[1]->key);
    }

    public function testFilterByKeyOnlyPublicLanguageTranslationList(): void
    {
        $data[] = LanguageLine::factory()->create([
            'key' => 'k1',
            'public' => true
        ]);
        $data[] = LanguageLine::factory()->create([
            'key' => 'k2',
            'public' => false
        ]);

        $this->response = $this->getJson('api/translations?key[]=k1');
        $this->assertListApiResponse(collect($data), 1);
        $this->assertEquals($this->response->getData()->data[0]->id, $data[0]->id);
        $this->assertEquals($this->response->getData()->data[0]->key, $data[0]->key);
    }

    private function assertListApiResponse(Collection $ids, int $count = 10): void
    {
        $this->response
            ->assertJsonCount($count, 'data')
            ->assertJsonStructure(['data' => [[
                'id',
                'group',
                'key',
                'text',
            ]]])
            ->assertJson(fn (AssertableJson $json) => $json->has('data', fn (AssertableJson $json) =>
                $json->each(fn (AssertableJson $json) =>  $json->where('id', fn ($json) =>
                    in_array($json, $ids->pluck('id')->toArray()) && !in_array($json, $this->privateTranslations->pluck('id')->toArray()))->etc()
                )->etc()
            )->etc())
            ->assertOk();
    }
}
