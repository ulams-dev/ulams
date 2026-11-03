<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Ulams\AuthoringMetrics\Models\CourseMetric;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\Tests\TestCase;
use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\CourseBuilder\Models\Session;

/** The first authoring metric endpoints: who may read, what comes back, tenant isolation (ADR 0190). */
class CourseMetricsApiTest extends TestCase
{
    use ForeignTenantToken;

    private function applied($author = null): array
    {
        $author ??= $this->author();
        $session = $this->toApplied($author, 'coffee-brewing.md', ['quiz', 'final']);

        return [$author, $session, (int) $session->refresh()->course_id];
    }

    public function testAnAdminReadsTheSnapshotWithStagesAndTheElementSplit(): void
    {
        [, $session, $course] = $this->applied();

        $data = $this->actingAs($this->admin(), 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertOk()->json('data');

        $this->assertSame($course, $data['course_id']);
        $this->assertSame($session->id, $data['session_id']);
        $this->assertNull($data['time']['author_minutes']);
        $this->assertGreaterThan(0, $data['cost']['llm_calls']);
        $this->assertSame($data['counts']['blocks'], $data['counts']['cited_blocks']);
        $this->assertSame($data['elements']['ai_elements'], $data['elements']['accepted_unchanged'] + $data['elements']['revised_on_request'] + $data['elements']['edited_by_author'] + $data['elements']['unknown']);
        $stages = collect($data['stages']);
        $this->assertContains('outline', $stages->pluck('stage')->all());
        $this->assertContains('lessons', $stages->pluck('stage')->all());
        $this->assertContains('apply', $stages->pluck('stage')->all());
        $this->assertSame(['exact'], $stages->pluck('quality')->unique()->values()->all());
    }

    public function testTheAuthorOfTheCourseReadsItAndNobodyElseDoes(): void
    {
        [$author, , $course] = $this->applied();

        $this->actingAs($author, 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertOk();
        $this->actingAs($this->author(), 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertForbidden();
        $this->actingAs($this->student(), 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertForbidden();
    }

    public function testTheListNeedsThePermissionAndFilters(): void
    {
        [$author, , $course] = $this->applied();
        [, , $other] = $this->applied();
        $admin = $this->admin();

        $this->actingAs($author, 'api')->getJson('/api/admin/authoring-metrics/courses')->assertForbidden();
        $ids = fn (array $r) => collect($r['data'])->pluck('course_id')->sort()->values()->all();
        $this->assertSame([$course, $other], $ids($this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/courses')->assertOk()->json()));
        $this->assertSame([$other], $ids($this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/courses?course_id[]=' . $other)->assertOk()->json()));
        $this->assertSame([], $ids($this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/courses?to=2000-01-01')->assertOk()->json()));
        $this->assertSame([$course, $other], $ids($this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/courses?from=2000-01-01&to=2100-01-01')->assertOk()->json()));
        $this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/courses?from=yesterday')->assertStatus(422);
    }

    public function testACourseNotBuiltWithTheBuilderIsNotFound(): void
    {
        $course = \Ulams\Courses\Models\Course::factory()->create();

        $this->actingAs($this->admin(), 'api')->getJson('/api/admin/authoring-metrics/courses/' . $course->getKey())->assertNotFound();
        $this->actingAs($this->admin(), 'api')->getJson('/api/admin/authoring-metrics/courses/999999')->assertNotFound();
    }

    public function testASnapshotMissingForAnOlderCourseIsComputedOnFirstRead(): void
    {
        [, , $course] = $this->applied();
        CourseMetric::query()->delete();

        $this->actingAs($this->admin(), 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertOk();

        $this->assertNotNull(CourseMetric::query()->find($course));
    }

    public static function routeProvider(): array
    {
        return ['list' => ['GET', 'api/admin/authoring-metrics/courses'], 'show' => ['GET', 'api/admin/authoring-metrics/courses/{course}']];
    }

    public function testEveryRouteOfThePackageIsListed(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/admin/authoring-metrics'))
            ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))->map(fn ($m) => "{$m} {$r->uri()}"))->sort()->values()->all();
        $listed = collect(self::routeProvider())->map(fn ($r) => "{$r[0]} {$r[1]}")->sort()->values()->all();

        $this->assertSame($listed, $routes, 'a new route of the package must be added to routeProvider with its isolation checks');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routeProvider')]
    public function testATokenOfAnotherTenantIsRejectedOnEveryRoute(string $method, string $uri): void
    {
        [, , $course] = $this->applied();
        $admin = $this->admin();
        \Illuminate\Support\Facades\Auth::forgetGuards(); // actingAs of the build above would answer as the author
        $uri = '/' . str_replace('{course}', (string) $course, $uri);

        $token = $admin->createToken('test')->accessToken;
        $this->sendAs($token, $method, $uri)->assertOk();
        $this->json($method, $uri)->assertUnauthorized();

        $this->switchToTenantWithOwnKeys();

        $this->sendAs($token, $method, $uri)->assertUnauthorized();
        $this->sendAs($admin->createToken('test')->accessToken, $method, $uri)->assertOk();
    }
}
