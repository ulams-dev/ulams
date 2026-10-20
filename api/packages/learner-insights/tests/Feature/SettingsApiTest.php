<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\LearnerInsights\Models\CourseSettings;
use Ulams\LearnerInsights\Tests\TestCase;

class SettingsApiTest extends TestCase
{
    public function testAnAdminOverridesThresholdsPerCourse(): void
    {
        [$course] = $this->courseWithTopic();
        $admin = $this->admin();
        $url = "/api/admin/learner-insights/courses/{$course->getKey()}/settings";

        $this->actingAs($admin, 'api')->getJson($url)->assertOk()->assertJsonPath('data.effective.inactivity.inactive_days', 7);

        $this->actingAs($admin, 'api')->putJson($url, ['rules' => ['inactivity' => ['inactive_days' => 5]]])
            ->assertOk()
            ->assertJsonPath('data.overrides.inactivity.inactive_days', 5)
            ->assertJsonPath('data.effective.inactivity.inactive_days', 5)
            ->assertJsonPath('data.effective.failed_attempts.failed_answers', 2);
        $this->assertSame(['inactivity' => ['inactive_days' => 5]], CourseSettings::query()->findOrFail($course->getKey())->rules);
        $this->assertSame($admin->getKey(), CourseSettings::query()->findOrFail($course->getKey())->updated_by);

        // another course keeps the defaults
        [$other] = $this->courseWithTopic();
        $this->actingAs($admin, 'api')->getJson("/api/admin/learner-insights/courses/{$other->getKey()}/settings")->assertJsonPath('data.effective.inactivity.inactive_days', 7);
    }

    public function testUnknownRulesAndBadValuesAreRejected(): void
    {
        [$course] = $this->courseWithTopic();
        $admin = $this->admin();
        $url = "/api/admin/learner-insights/courses/{$course->getKey()}/settings";

        $this->actingAs($admin, 'api')->putJson($url, ['rules' => ['inactivity' => ['inactive_days' => 0]]])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson($url, ['rules' => ['inactivity' => ['inactive_days' => 'soon']]])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson($url, ['rules' => ['inactivity' => ['enabled' => 'maybe']]])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson($url, [])->assertStatus(422);
        // an unknown rule is ignored, not stored
        $this->actingAs($admin, 'api')->putJson($url, ['rules' => ['mind_reading' => ['enabled' => true], 'repetition' => ['replays' => 4]]])->assertOk();
        $this->assertSame(['repetition' => ['replays' => 4]], CourseSettings::query()->findOrFail($course->getKey())->rules);
    }

    public function testOnlyThePermissionHolderMayManage(): void
    {
        [$course] = $this->courseWithTopic();
        $url = "/api/admin/learner-insights/courses/{$course->getKey()}/settings";

        $this->actingAs($this->learner($course), 'api')->getJson($url)->assertForbidden();
        $this->actingAs($this->tutor(), 'api')->putJson($url, ['rules' => ['inactivity' => ['inactive_days' => 3]]])->assertForbidden();
        Auth::forgetGuards();
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->admin(), 'api')->getJson('/api/admin/learner-insights/courses/99999/settings')->assertNotFound();
        $this->actingAs($this->admin(), 'api')->getJson('/api/admin/learner-insights/settings')->assertOk()->assertJsonPath('data.rules.slow_element.min_cohort', 10);
        $this->assertNull(CourseSettings::query()->find($course->getKey()));
    }

    /** Tenants have their own Passport key pair (ADR 0007): an admin token of tenant A is useless on tenant B. */
    public function testAnAdminTokenOfAnotherTenantIsRejected(): void
    {
        [$course] = $this->courseWithTopic();
        $token = $this->admin()->createToken('test')->accessToken;
        $url = "/api/admin/learner-insights/courses/{$course->getKey()}/settings";
        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->putJson($url, ['rules' => ['inactivity' => ['inactive_days' => 4]]], $headers)->assertOk();
        Auth::forgetGuards();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();

        $this->putJson($url, ['rules' => ['inactivity' => ['inactive_days' => 9]]], $headers)->assertUnauthorized();
        $this->assertSame(4, CourseSettings::query()->findOrFail($course->getKey())->rules['inactivity']['inactive_days']);
    }
}
