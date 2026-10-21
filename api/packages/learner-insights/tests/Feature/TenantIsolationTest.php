<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Tests\Support\Journey;
use Ulams\LearnerInsights\Tests\Support\SwitchesTenantKeys;
use Ulams\LearnerInsights\Tests\TestCase;

/**
 * Every route of the package is listed here with a valid request, and the list is checked against the
 * router: a new endpoint that is not added fails `testEveryRouteOfThePackageIsListed`, so it cannot skip
 * the isolation checks. Per route: the token of tenant A works in A; tenant B (its own Passport key
 * pair) rejects it with 401; the same user with a token of B is let in; and no token is 401.
 *
 * Between learners of one tenant: a learner never reaches another learner's assignments, presentation
 * or export; an author of one course gets 403 on the analytics of another.
 */
class TenantIsolationTest extends TestCase
{
    use SwitchesTenantKeys;

    private const ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    /**
     * Admin routes use an administrator, learner routes an enrolled student.
     *
     * @return array<string,array{0:string,1:string,2:array<string,mixed>,3:bool,4:int}> method, uri, body, as admin, status in tenant A
     */
    public static function routes(): array
    {
        $rows = [
            // learner endpoints (api/learner-insights)
            ['POST', 'api/learner-insights/signals', ['type' => 'media.replayed', 'topicId' => '{topic}', 'ref' => '3f1b6f7e-6c5e-4b1d-9d0e-2c6b9c8a1a11'], false, 202],
            ['GET', 'api/learner-insights/courses/{course}/topics/{topic}/support', [], false, 200],
            ['GET', 'api/learner-insights/courses/{course}/presentation', [], false, 200],
            ['PUT', 'api/learner-insights/courses/{course}/presentation', ['hints' => 2], false, 200],
            ['POST', 'api/learner-insights/assignments/{assignment}/feedback', ['helpful' => true], false, 404],
            ['POST', 'api/learner-insights/assignments/{assignment}/hide', [], false, 404],
            ['POST', 'api/learner-insights/assignments/{assignment}/reflection', ['text' => 'My own words about the topic.'], false, 404],
            ['POST', 'api/learner-insights/nudges/opened', ['n' => 'not-a-genuine-token'], false, 404],
            ['GET', 'api/learner-insights/me', [], false, 200],
            ['PUT', 'api/learner-insights/me/settings', ['help' => true], false, 200],
            ['GET', 'api/learner-insights/me/export', [], false, 200],
            ['DELETE', 'api/learner-insights/me', [], false, 200],
            // admin endpoints (api/admin/learner-insights)
            ['GET', 'api/admin/learner-insights/settings', [], true, 200],
            ['GET', 'api/admin/learner-insights/courses/{course}/settings', [], true, 200],
            ['PUT', 'api/admin/learner-insights/courses/{course}/settings', ['rules' => ['inactivity' => ['inactive_days' => 5]]], true, 200],
            ['GET', 'api/admin/learner-insights/courses/{course}/summary', [], true, 200],
            ['GET', 'api/admin/learner-insights/courses/{course}/elements', [], true, 200],
            ['GET', 'api/admin/learner-insights/courses/{course}/learners', [], true, 200],
            ['GET', 'api/admin/learner-insights/learners/{user}/courses/{course}/timeline', [], true, 200],
            ['POST', 'api/admin/learner-insights/backfill', [], true, 202],
        ];

        return array_combine(array_map(fn ($r) => "$r[0] $r[1]", $rows), $rows);
    }

    public static function routeProvider(): array
    {
        return self::routes();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routeProvider')]
    public function testATokenOfAnotherTenantIsRejectedOnEveryRoute(string $method, string $uri, array $body, bool $asAdmin, int $status): void
    {
        Bus::fake();
        [$course, $topic] = $this->courseWithTopic();
        $learner = $this->learner($course);
        $user = $asAdmin ? $this->admin() : $learner;
        $uri = '/' . strtr($uri, ['{course}' => $course->getKey(), '{topic}' => $topic->getKey(), '{user}' => $learner->getKey(), '{assignment}' => self::ULID]);
        $body = json_decode(strtr(json_encode($body), ['"{topic}"' => (string) $topic->getKey()]), true);

        $tokenA = $user->createToken('test')->accessToken;
        $this->sendAs($tokenA, $method, $uri, $body)->assertStatus($status);

        $this->switchToTenantWithOwnKeys();

        $this->sendAs($tokenA, $method, $uri, $body)->assertUnauthorized();
        $this->sendAs($user->createToken('test')->accessToken, $method, $uri, $body)->assertStatus($status);
        $this->json($method, $uri, $body)->assertUnauthorized();
    }

    public function testEveryRouteOfThePackageIsListed(): void
    {
        $registered = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (!preg_match('#^api/(admin/)?learner-insights(/|$)#', $route->uri())) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $registered[] = "$method {$route->uri()}";
            }
        }
        sort($registered);
        $listed = array_keys(self::routes());
        sort($listed);

        $this->assertNotEmpty($registered);
        $this->assertSame([], array_values(array_diff($registered, $listed)), 'a route of learner-insights is missing from TenantIsolationTest::routes()');
        $this->assertSame([], array_values(array_diff($listed, $registered)), 'TenantIsolationTest::routes() lists a route that no longer exists');
    }

    public function testEveryRouteIsBehindAuthentication(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (preg_match('#^api/(admin/)?learner-insights(/|$)#', $route->uri())) {
                $this->assertContains('auth:api', $route->gatherMiddleware(), "{$route->uri()} is reachable without a token");
            }
        }
    }

    public function testALearnerNeverReachesAnotherLearnersPresentation(): void
    {
        [$course] = $this->courseWithTopic();
        $a = $this->learner($course);
        $b = $this->learner($course);
        $url = "/api/learner-insights/courses/{$course->getKey()}/presentation";

        $this->actingAs($a, 'api')->putJson($url, ['hints' => 3, 'density' => 'compact'])->assertOk()->assertJsonPath('data.overrides.hints', 3);

        $this->actingAs($b, 'api')->getJson($url)->assertOk()->assertJsonPath('data.overrides', []);
        // an enrolment in course X is no access to the presentation of course Y
        $this->actingAs($a, 'api')->getJson('/api/learner-insights/courses/' . Course::factory()->create(['status' => 'published'])->getKey() . '/presentation')->assertForbidden();
    }

    public function testAssignmentsOfOneLearnerAreNotFoundForAnother(): void
    {
        [$course] = $this->courseWithTopic();
        $other = $this->learner($course);

        foreach (['feedback' => ['helpful' => true], 'hide' => [], 'reflection' => ['text' => str_repeat('a', 20)]] as $action => $body) {
            $this->actingAs($other, 'api')->postJson('/api/learner-insights/assignments/' . self::ULID . "/$action", $body)->assertNotFound();
        }
    }

    public function testTheMeEndpointsReturnOnlyTheCallersCourses(): void
    {
        [$courseA, $topicA] = $this->courseWithTopic();
        [$courseB] = $this->courseWithTopic();
        $a = Journey::learner($this, $courseA, ['quiz' => $topicA]);
        $a->answer('quiz', 3, false)->answer('quiz', 3, false);
        $b = $this->learner($courseB);

        $this->actingAs($b, 'api')->getJson('/api/learner-insights/me')->assertOk()->assertJsonPath('data.courses', []);
        $this->actingAs($a->user, 'api')->getJson('/api/learner-insights/me')->assertOk()
            ->assertJsonPath('data.courses.0.courseId', $courseA->getKey())
            ->assertJsonPath('data.courses.0.status', 'struggling');
    }

    public function testAnAuthorOfOneCourseGetsNoAnalyticsOfAnother(): void
    {
        [$mine] = $this->courseWithTopic();
        [$theirs] = $this->courseWithTopic();
        $author = $this->tutor();
        $mine->authors()->attach($author->getKey());
        config(['learner_insights.instructors_see_individuals' => true]);

        foreach (['summary', 'elements', 'learners'] as $path) {
            $this->actingAs($author, 'api')->getJson("/api/admin/learner-insights/courses/{$mine->getKey()}/$path")->assertOk();
            $this->actingAs($author, 'api')->getJson("/api/admin/learner-insights/courses/{$theirs->getKey()}/$path")->assertForbidden();
        }
        $this->actingAs($author, 'api')->getJson("/api/admin/learner-insights/learners/{$author->getKey()}/courses/{$mine->getKey()}/timeline")->assertForbidden();
        Auth::forgetGuards();
    }
}
