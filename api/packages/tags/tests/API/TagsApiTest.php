<?php

namespace Ulams\Tags\Tests\API;

use Ulams\Tags\Models\Tag;
use Ulams\Tags\Tests\TestCase;
use Ulams\Tags\Database\Seeders\TagsPermissionSeeder;
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
        Config::set('ulams_tags.tag_model_map.test', 'test');
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
        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/tags/' . $tags[0]->id);
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
        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/tags/unique')
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

    public function testAdminRoutesRequireAuthentication() : void
    {
        $tag = Tag::factory(['morphable_type' => 'test', 'morphable_id' => 1])->create();

        $this->json('GET', '/api/admin/tags/unique')->assertUnauthorized();
        $this->json('GET', '/api/admin/tags/' . $tag->getKey())->assertUnauthorized();
        $this->json('POST', '/api/admin/tags', [
            'model_type' => 'test',
            'model_id' => 1,
            'tags' => [['title' => 'guest']],
        ])->assertUnauthorized();
        $this->json('DELETE', '/api/admin/tags', ['tags' => [$tag->getKey()]])->assertUnauthorized();

        $this->assertDatabaseHas('tags', ['id' => $tag->getKey()]);
        $this->assertDatabaseMissing('tags', ['title' => 'guest']);
    }

    public function testAdminRoutesRequirePermissions() : void
    {
        $tag = Tag::factory(['morphable_type' => 'test', 'morphable_id' => 1])->create();
        $student = config('auth.providers.users.model')::factory()->create();
        $student->guard_name = 'api';
        $student->assignRole('student');

        $this->actingAs($student, 'api')->json('GET', '/api/admin/tags/unique')->assertForbidden();
        $this->actingAs($student, 'api')->json('GET', '/api/admin/tags/' . $tag->getKey())->assertForbidden();
        $this->actingAs($student, 'api')->json('POST', '/api/admin/tags', [
            'model_type' => 'test',
            'model_id' => 1,
            'tags' => [['title' => 'student']],
        ])->assertForbidden();
        $this->actingAs($student, 'api')->json('DELETE', '/api/admin/tags', ['tags' => [$tag->getKey()]])
            ->assertForbidden();

        $this->assertDatabaseHas('tags', ['id' => $tag->getKey()]);
    }

    public function testTutorCanListTagsInAdmin() : void
    {
        $tutor = config('auth.providers.users.model')::factory()->create();
        $tutor->guard_name = 'api';
        $tutor->assignRole('tutor');

        $this->actingAs($tutor, 'api')->json('GET', '/api/admin/tags/unique')->assertOk();
    }
}
