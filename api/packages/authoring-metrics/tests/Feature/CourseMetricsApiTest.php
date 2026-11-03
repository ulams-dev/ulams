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
        $this->assertSame(\Ulams\Courses\Models\Course::query()->find($course)->title, $data['course_title']);
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

    public function testTheExportIsOneRowPerCourseInCsvAndJsonLines(): void
    {
        [$author, , $course] = $this->applied();
        $admin = $this->admin();
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $csv = $this->actingAs($admin, 'api')->get('/api/admin/authoring-metrics/courses/export')->assertOk();
        $this->assertStringStartsWith('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('authoring-metrics-', $csv->headers->get('Content-Disposition'));
        $lines = array_values(array_filter(explode("\n", $csv->streamedContent())));
        $header = str_getcsv($lines[0], ',', '"', '');
        $this->assertCount(2, $lines, 'a header and one course');
        $row = array_combine($header, str_getcsv($lines[1], ',', '"', ''));
        $this->assertSame((string) $course, $row['course_id']);
        $this->assertSame('2', $row['method_version']);
        $this->assertSame('', $row['author_minutes'], 'not recorded is an empty cell, not a zero');
        foreach (['generation_seconds', 'cost_build_micro_usd', 'ai_accepted_unchanged', 'ai_unknown', 'learner_minutes_estimate'] as $column) {
            $this->assertContains($column, $header);
        }

        $jsonl = $this->actingAs($admin, 'api')->get('/api/admin/authoring-metrics/courses/export?format=jsonl')->assertOk();
        $this->assertStringStartsWith('application/x-ndjson', $jsonl->headers->get('Content-Type'));
        $decoded = json_decode(trim($jsonl->streamedContent()), true);
        $this->assertSame($course, $decoded['course_id']);
        $this->assertNull($decoded['author_minutes']);

        $this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/courses/export?format=xml')->assertStatus(422);
        $this->actingAs($admin, 'api')->get('/api/admin/authoring-metrics/courses/export?course_id[]=999999')->assertOk();
        $this->actingAs($author, 'api')->get('/api/admin/authoring-metrics/courses/export')->assertForbidden();
    }

    public function testAnEmptyExportStillHasItsHeader(): void
    {
        $csv = $this->actingAs($this->admin(), 'api')->get('/api/admin/authoring-metrics/courses/export')->assertOk()->streamedContent();

        $this->assertStringStartsWith('course_id,session_id,method_version,', $csv);
        $this->assertCount(1, array_filter(explode("\n", $csv)));
    }

    /** method, uri, body (with {minute}), the status in this tenant */
    public static function routeProvider(): array
    {
        return [
            'list' => ['GET', 'api/admin/authoring-metrics/courses', [], 200],
            'export' => ['GET', 'api/admin/authoring-metrics/courses/export', [], 200],
            'summary' => ['GET', 'api/admin/authoring-metrics/summary', [], 200],
            'show' => ['GET', 'api/admin/authoring-metrics/courses/{course}', [], 200],
            'status' => ['GET', 'api/admin/authoring-metrics/activity/status', [], 200],
            'record' => ['POST', 'api/admin/authoring-metrics/activity', ['course_id' => '{course}', 'minutes' => ['{minute}'], 'surface' => 'studio', 'tab_nonce' => 'AAAAAAAAAAAAAAAAAAAAAA'], 200],
            'delete' => ['DELETE', 'api/admin/authoring-metrics/activity', [], 200],
        ];
    }

    public function testEveryRouteOfThePackageIsListed(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/admin/authoring-metrics'))
            ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))->map(fn ($m) => "{$m} {$r->uri()}"))->sort()->values()->all();
        $listed = collect(self::routeProvider())->map(fn ($r) => "{$r[0]} {$r[1]}")->sort()->values()->all();

        $this->assertSame($listed, $routes, 'a new route of the package must be added to routeProvider with its isolation checks');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routeProvider')]
    public function testATokenOfAnotherTenantIsRejectedOnEveryRoute(string $method, string $uri, array $body, int $status): void
    {
        config(['authoring_metrics.review_time' => true]);
        [, , $course] = $this->applied();
        $admin = $this->admin();
        \Illuminate\Support\Facades\Auth::forgetGuards(); // actingAs of the build above would answer as the author
        $uri = '/' . str_replace('{course}', (string) $course, $uri);
        $body = json_decode(str_replace(['"{course}"', '{minute}'], [(string) $course, now()->subMinute()->toIso8601String()], json_encode($body)), true);

        $token = $admin->createToken('test')->accessToken;
        $this->sendAs($token, $method, $uri, $body)->assertStatus($status);
        $this->json($method, $uri, $body)->assertUnauthorized();

        $this->switchToTenantWithOwnKeys();

        $this->sendAs($token, $method, $uri, $body)->assertUnauthorized();
        $this->sendAs($admin->createToken('test')->accessToken, $method, $uri, $body)->assertStatus($status);
    }
}
