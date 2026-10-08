<?php

namespace EscolaLms\Tags\Tests\API;

use EscolaLms\Tags\Models\Tag;
use EscolaLms\Tags\Tests\TestCase;
use EscolaLms\Tags\Database\Seeders\TagsPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;

class TagsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TagsPermissionSeeder::class);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        Config::set('escolalms_tags.tag_model_map.test', 'test');
    }

    public function testTagsInsert() : void
    {
        // Set value for test
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/tags', [
            'model_type' => 'test',
            'model_id' => 1,
            'tags' => [
                ['title' => 'test'],
            ]
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'title' => 'test',
            ]);
    }

    public function testTagsIndex() : void
    {
        $response = $this->json('GET', '/api/tags');
        $response->assertOk();
    }

    public function testTagShow() : void
    {
        $tagActiveCourse = Tag::factory([
            'morphable_type' => 'test',
            'morphable_id' => 1
        ])->create();

        // Set value for test
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/tags', [
            'model_type' => 'test',
            'model_id' => 1,
            'tags' => [
                ['title' => $tagActiveCourse->title]
            ]
        ]);
        $tags = $response->getData()->data;
        $response = $this->json('GET', '/api/admin/tags/' . $tags[0]->id);
        $response->assertOk();
        $this->assertIsObject($response->getData()->data);
        $response->assertJsonPath('data.id', $tags[0]->id);
    }

    public function testTagDestroy() : void
    {
        $tag1 = Tag::factory([
            'morphable_type' => 'test',
            'morphable_id' => 1
        ])->create();
        $tag2 = Tag::factory([
            'morphable_type' => 'test',
            'morphable_id' => 1
        ])->create();
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/tags', [
            'model_type' => 'test',
            'model_id' => 1,
            'tags' => [
                ['title' => $tag1->title],
                ['title' => $tag2->title]
            ]
        ]);
        $response->assertStatus(200);
        $tags = $response->getData()->data;
        $response = $this->actingAs($this->user, 'api')->json('DELETE', '/api/admin/tags', [
            'tags' => array_map(function ($tag) {
                return $tag->id;
            }, $tags)

        ]);
        $response->assertStatus(200);
    }

    public function testTagsUnique() : void
    {
        $response = $this->json('GET', '/api/tags/unique')
            ->assertOk();

        $temp_array = $key_array = [];
        foreach ($response->getData()->data as $key => $val) {
            if (!in_array($val->title, $key_array)) {
                $key_array[$key] = $val->title;
                $temp_array[$key] = $val;
            }
        }
        $this->assertTrue(count($temp_array) === count($response->getData()->data));
    }

    public function testTagsUniqueAdmin() : void
    {
        $response = $this->json('GET', '/api/admin/tags/unique')
            ->assertOk();

        $temp_array = $key_array = [];
        foreach ($response->getData()->data as $key => $val) {
            if (!in_array($val->title, $key_array)) {
                $key_array[$key] = $val->title;
                $temp_array[$key] = $val;
            }
        }
        $this->assertTrue(count($temp_array) === count($response->getData()->data));
    }
}
