<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Support\TokenScopes;
use Ulams\AuthoringMetrics\Tests\TestCase;

/** The activity endpoint (AM-5): off by default, who may post, what is refused, and that nothing identifies a person. */
class ReviewTimeApiTest extends TestCase
{
    private const NONCE = 'AAAAAAAAAAAAAAAAAAAAAA';

    private function applied(): array
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        Auth::forgetGuards();

        return [$author, (int) $session->refresh()->course_id];
    }

    private function body(int $course, array $over = []): array
    {
        return $over + ['course_id' => $course, 'minutes' => [now()->subMinute()->toIso8601String()], 'surface' => 'studio', 'tab_nonce' => self::NONCE];
    }

    public function testWhileTheSettingIsOffItAnswers404AndStoresNothing(): void
    {
        [$author, $course] = $this->applied();
        $this->assertFalse((bool) config('authoring_metrics.review_time'));

        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course))->assertNotFound();

        $this->assertSame(0, DB::table('authoring_activity_minutes')->count());
        $this->actingAs($author, 'api')->getJson('/api/admin/authoring-metrics/activity/status')->assertOk()->assertJsonPath('data.enabled', false);
    }

    public function testTheAuthorOfTheCourseReportsMinutesWhenItIsOn(): void
    {
        config(['authoring_metrics.review_time' => true]);
        [$author, $course] = $this->applied();

        $this->actingAs($author, 'api')->getJson('/api/admin/authoring-metrics/activity/status')->assertOk()
            ->assertJsonPath('data.enabled', true)->assertJsonPath('data.maxMinutesPerCall', 10)->assertJsonPath('data.maxAgeMinutes', 15);
        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course, ['minutes' => [now()->subMinutes(2)->toIso8601String(), now()->subMinute()->toIso8601String()]]))
            ->assertOk()->assertJsonPath('data.accepted', 2);
        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course, ['minutes' => [now()->subMinute()->toIso8601String()]]))
            ->assertOk()->assertJsonPath('data.accepted', 0);

        $row = (array) DB::table('authoring_activity_minutes')->first();
        $this->assertSame(['course_id', 'minute_start', 'surface', 'tab_nonce'], collect(array_keys($row))->sort()->values()->all());
        $this->assertSame(2, DB::table('authoring_activity_minutes')->count());
    }

    public function testAnAdministratorMayReportForAnyBuilderCourseAndAnotherAuthorMayNot(): void
    {
        config(['authoring_metrics.review_time' => true]);
        [, $course] = $this->applied();

        $this->actingAs($this->author(), 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course))->assertForbidden();
        $this->actingAs($this->student(), 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course, ['surface' => 'admin']))->assertForbidden();
        $this->actingAs($this->admin(), 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course, ['surface' => 'admin']))->assertOk();
        $this->assertSame(1, DB::table('authoring_activity_minutes')->where('surface', 'admin')->count());
    }

    public function testACourseThatDoesNotExistHereIsNotFound(): void
    {
        config(['authoring_metrics.review_time' => true]);
        [$author] = $this->applied();
        $notBuilt = \Ulams\Courses\Models\Course::factory()->create();

        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body(999999))->assertNotFound();
        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body((int) $notBuilt->getKey()))->assertNotFound();
        $this->assertSame(0, DB::table('authoring_activity_minutes')->count());
    }

    public function testInvalidMinutesAreRefused(): void
    {
        config(['authoring_metrics.review_time' => true]);
        [$author, $course] = $this->applied();
        $post = fn (array $over) => $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course, $over));

        $post(['minutes' => [now()->addMinutes(2)->toIso8601String()]])->assertStatus(422);
        $post(['minutes' => [now()->subMinutes(20)->toIso8601String()]])->assertStatus(422);
        $post(['minutes' => array_map(fn ($i) => now()->subMinutes($i)->toIso8601String(), range(1, 11))])->assertStatus(422);
        $post(['minutes' => []])->assertStatus(422);
        $post(['minutes' => ['not a date']])->assertStatus(422);
        $post(['surface' => 'mobile'])->assertStatus(422);
        $post(['tab_nonce' => 'short'])->assertStatus(422);
        $post(['tab_nonce' => str_repeat('é', 22)])->assertStatus(422);
        $this->assertSame(0, DB::table('authoring_activity_minutes')->count());
    }

    public function testExtraFieldsThatCouldIdentifyAPersonAreIgnoredNotStored(): void
    {
        config(['authoring_metrics.review_time' => true]);
        [$author, $course] = $this->applied();

        $this->actingAs($author, 'api')->withHeaders(['User-Agent' => 'Mozilla/5.0 (secret)'])
            ->postJson('/api/admin/authoring-metrics/activity', $this->body($course, ['user_id' => 987654, 'element_id' => 'element-xyz', 'email' => 'a@b.test']), ['REMOTE_ADDR' => '203.0.113.9'])->assertOk();

        $dump = json_encode(DB::table('authoring_activity_minutes')->get()->all());
        foreach (['203.0.113.9', 'Mozilla', 'secret', '987654', 'a@b.test', 'element-xyz'] as $needle) {
            $this->assertStringNotContainsString($needle, $dump);
        }
    }

    public function testAScopedTokenCannotReportOrDeleteReviewTime(): void
    {
        foreach ([['POST', 'api/admin/authoring-metrics/activity'], ['DELETE', 'api/admin/authoring-metrics/activity'], ['GET', 'api/admin/authoring-metrics/activity/status']] as [$method, $uri]) {
            $this->assertSame('none', TokenScopes::requirement($uri, $method)['area'], "{$method} {$uri}");
        }
        $this->assertSame('builder', TokenScopes::requirement('api/admin/authoring-metrics/courses', 'GET')['area'], 'the figures stay readable with a builder scope');
    }

    public function testDeletingRecordedReviewTimeNeedsThePermissionAndKeepsTheRest(): void
    {
        config(['authoring_metrics.review_time' => true]);
        [$author, $course] = $this->applied();
        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course))->assertOk();
        $admin = $this->admin();
        Auth::forgetGuards();

        $this->actingAs($author, 'api')->deleteJson('/api/admin/authoring-metrics/activity')->assertForbidden();
        $this->assertSame(1, DB::table('authoring_activity_minutes')->count());
        $this->actingAs($admin, 'api')->deleteJson('/api/admin/authoring-metrics/activity')->assertOk()->assertJsonPath('data.minutes', 1);

        $this->assertSame(0, DB::table('authoring_activity_minutes')->count());
        $this->actingAs($admin, 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertOk()->assertJsonPath('data.time.author_minutes', null);
    }

    public function testSwitchingItOffStopsCollectionAtOnce(): void
    {
        config(['authoring_metrics.review_time' => true]);
        [$author, $course] = $this->applied();
        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course))->assertOk();

        config(['authoring_metrics.review_time' => false]);

        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course, ['minutes' => [now()->subMinutes(2)->toIso8601String()]]))->assertNotFound();
        $this->assertSame(1, DB::table('authoring_activity_minutes')->count());
    }

    public function testTheCourseMetricShowsAuthorTimeOnceRecorded(): void
    {
        config(['authoring_metrics.review_time' => true]);
        [$author, $course] = $this->applied();
        $this->actingAs($author, 'api')->postJson('/api/admin/authoring-metrics/activity', $this->body($course, ['minutes' => [now()->subMinutes(2)->toIso8601String(), now()->subMinute()->toIso8601String()]]))->assertOk();
        app(\Ulams\AuthoringMetrics\Services\CourseMetrics::class)->recompute($course);

        $this->actingAs($author, 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertOk()->assertJsonPath('data.time.author_minutes', 2);
    }
}
