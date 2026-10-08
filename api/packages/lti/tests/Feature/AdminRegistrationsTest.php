<?php

namespace Ulams\Lti\Tests\Feature;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Models\LtiPlatform;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Tests\TestCase;

class AdminRegistrationsTest extends TestCase
{
    use CreatesUsers;

    public function testAdminsRegisterToolsAndGetOurEndpoints(): void
    {
        $admin = $this->makeAdmin();

        $tool = $this->actingAs($admin, 'api')->postJson('/api/admin/lti/tools', [
            'name' => 'GeoGebra',
            'oidc_login_url' => 'https://www.geogebra.org/lti/login',
            'launch_url' => 'https://www.geogebra.org/lti/launch',
            'jwks_url' => 'https://www.geogebra.org/lti/jwks',
            'custom' => ['app' => 'graphing'],
        ])->assertCreated()->json('data');
        $this->assertNotEmpty($tool['client_id']);
        $this->assertNotEmpty($tool['deployment_id']);

        $this->actingAs($admin, 'api')->putJson("/api/admin/lti/tools/{$tool['id']}", ['enabled' => false])
            ->assertOk()->assertJsonPath('data.enabled', false);
        $this->actingAs($admin, 'api')->getJson('/api/admin/lti/tools')->assertOk()->assertJsonPath('data.0.name', 'GeoGebra');

        $endpoints = $this->actingAs($admin, 'api')->getJson('/api/admin/lti/endpoints')->assertOk()->json('data');
        $this->assertSame('https://lms.example.test', $endpoints['issuer']);
        $this->assertSame('https://lms.example.test/api/lti/jwks', $endpoints['jwks_url']);
        $this->assertSame('https://lms.example.test/api/lti/platform/authorize', $endpoints['platform']['oidc_auth_url']);
        $this->assertSame('https://lms.example.test/api/lti/tool/login', $endpoints['tool']['oidc_login_url']);

        $this->actingAs($admin, 'api')->deleteJson("/api/admin/lti/tools/{$tool['id']}")->assertOk();
    }

    public function testToolsNeedAKeySourceAndValidUrls(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/lti/tools', [
            'name' => 'X', 'oidc_login_url' => 'not a url', 'launch_url' => 'https://x.test/launch',
        ])->assertUnprocessable()->assertJsonValidationErrors(['oidc_login_url', 'jwks_url', 'public_key']);
    }

    public function testAToolUsedByTopicsCannotBeDeleted(): void
    {
        $tool = LtiTool::factory()->create();
        $this->courseWithLink($tool);

        $this->actingAs($this->makeAdmin(), 'api')->deleteJson("/api/admin/lti/tools/{$tool->getKey()}")->assertStatus(409);
    }

    public function testAdminsRegisterPlatforms(): void
    {
        $admin = $this->makeAdmin();
        $payload = [
            'name' => 'School Moodle',
            'issuer' => 'https://moodle.school.test',
            'client_id' => 'abc123',
            'deployment_ids' => ['1'],
            'auth_login_url' => 'https://moodle.school.test/mod/lti/auth.php',
            'auth_token_url' => 'https://moodle.school.test/mod/lti/token.php',
            'jwks_url' => 'https://moodle.school.test/mod/lti/certs.php',
        ];

        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/lti/platforms', $payload)->assertCreated()->json('data.id');
        $this->actingAs($admin, 'api')->postJson('/api/admin/lti/platforms', $payload)->assertUnprocessable();
        $this->actingAs($admin, 'api')->putJson("/api/admin/lti/platforms/{$id}", ['deployment_ids' => ['1', '2']])
            ->assertOk()->assertJsonPath('data.deployment_ids', ['1', '2']);
        $this->actingAs($admin, 'api')->getJson("/api/admin/lti/platforms/{$id}")->assertOk();
        $this->actingAs($admin, 'api')->deleteJson("/api/admin/lti/platforms/{$id}")->assertOk();
        $this->assertNull(LtiPlatform::query()->find($id));
    }

    public function testRegistrationsNeedTheLtiManagePermission(): void
    {
        $tool = LtiTool::factory()->create();
        $platform = LtiPlatform::factory()->create();

        $this->getJson('/api/admin/lti/tools')->assertUnauthorized();
        foreach ([$this->makeStudent(), $this->makeInstructor()] as $user) {
            $this->actingAs($user, 'api')->getJson('/api/admin/lti/tools')->assertForbidden();
            $this->actingAs($user, 'api')->getJson("/api/admin/lti/tools/{$tool->getKey()}")->assertForbidden();
            $this->actingAs($user, 'api')->postJson('/api/admin/lti/tools', [])->assertForbidden();
            $this->actingAs($user, 'api')->getJson('/api/admin/lti/platforms')->assertForbidden();
            $this->actingAs($user, 'api')->deleteJson("/api/admin/lti/platforms/{$platform->getKey()}")->assertForbidden();
            $this->actingAs($user, 'api')->getJson('/api/admin/lti/endpoints')->assertForbidden();
        }
    }

    public function testAnExternalToolTopicIsCreatedThroughTheTopicApi(): void
    {
        $tool = LtiTool::factory()->create();
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);

        $response = $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/topics', [
            'title' => 'Graphing activity',
            'lesson_id' => $lesson->getKey(),
            'topicable_type' => LtiLink::class,
            'lti_tool_id' => $tool->getKey(),
            'url' => 'https://tool.example.test/lti/launch?activity=7',
            'custom' => ['activity' => '7'],
        ]);

        $response->assertCreated();
        $topic = Topic::query()->with('topicable')->findOrFail($response->json('data.id'));
        $this->assertInstanceOf(LtiLink::class, $topic->topicable);
        $this->assertSame($tool->getKey(), $topic->topicable->lti_tool_id);
        $this->assertSame($tool->name, $response->json('data.topicable.tool_name'));

        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/topics', [
            'title' => 'Broken', 'lesson_id' => $lesson->getKey(), 'topicable_type' => LtiLink::class, 'lti_tool_id' => 999999,
        ])->assertUnprocessable();
    }
}
