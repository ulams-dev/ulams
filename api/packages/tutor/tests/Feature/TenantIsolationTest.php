<?php

namespace Ulams\Tutor\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Tests\Support\SwitchesTenantKeys;
use Ulams\Tutor\Models\Conversation;
use Ulams\Tutor\Tests\TestCase;

/**
 * Every route of the tutor is listed with a valid request, and the list is checked against the router:
 * a new endpoint that is not added fails `testEveryRouteOfThePackageIsListed`. Per route: the token of
 * tenant A works in A; tenant B (its own Passport key pair) rejects it with 401; the same user with a
 * token of B is let in; no token is 401. Between learners of one tenant: another learner gets 404 on
 * a conversation, its deletion and its run events; an author of one course gets 403 on the analytics
 * of another.
 */
class TenantIsolationTest extends TestCase
{
    use SwitchesTenantKeys;

    private const ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    /** @return array<string,array{0:string,1:string,2:array<string,mixed>,3:bool,4:int}> method, uri, body, as admin, status in tenant A */
    public static function routes(): array
    {
        $rows = [
            ['GET', 'api/tutor/courses/{course}/status', [], false, 200],
            ['POST', 'api/tutor/courses/{course}/messages', ['text' => 'What does this course cover?'], false, 200],
            ['GET', 'api/tutor/courses/{course}/conversations', [], false, 200],
            ['GET', 'api/tutor/conversations/{conversation}', [], false, 404],
            ['DELETE', 'api/tutor/conversations/{conversation}', [], false, 404],
            ['GET', 'api/tutor/runs/{run}/events', [], false, 404],
            ['GET', 'api/admin/tutor/courses/{course}/analytics', [], true, 200],
        ];

        return array_combine(array_map(fn ($r) => "$r[0] $r[1]", $rows), $rows);
    }

    public static function routeProvider(): array
    {
        return self::routes();
    }

    private function course(): Course
    {
        return Course::factory()->create(['status' => 'published']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routeProvider')]
    public function testATokenOfAnotherTenantIsRejectedOnEveryRoute(string $method, string $uri, array $body, bool $asAdmin, int $status): void
    {
        $course = $this->course();
        $learner = $this->student();
        $learner->courses()->attach($course->getKey());
        $user = $asAdmin ? $this->admin() : $learner;
        $uri = '/' . strtr($uri, ['{course}' => $course->getKey(), '{conversation}' => self::ULID, '{run}' => self::ULID]);

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
            if (!preg_match('#^api/(admin/)?tutor(/|$)#', $route->uri())) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $registered[] = "$method {$route->uri()}";
            }
            $this->assertContains('auth:api', $route->gatherMiddleware(), "{$route->uri()} is reachable without a token");
        }
        sort($registered);
        $listed = array_keys(self::routes());
        sort($listed);

        $this->assertNotEmpty($registered);
        $this->assertSame([], array_values(array_diff($registered, $listed)), 'a route of the tutor is missing from TenantIsolationTest::routes()');
        $this->assertSame([], array_values(array_diff($listed, $registered)), 'TenantIsolationTest::routes() lists a route that no longer exists');
    }

    public function testAnotherLearnerCannotReadDeleteOrFollowAConversation(): void
    {
        $course = $this->course();
        $a = $this->student();
        $b = $this->student();
        $a->courses()->attach($course->getKey());
        $b->courses()->attach($course->getKey());

        $id = $this->actingAs($a, 'api')->postJson("/api/tutor/courses/{$course->getKey()}/messages", ['text' => 'What does this course cover?'])->assertOk()->json('data.conversationId');
        $this->assertNotNull(Conversation::query()->find($id));

        $this->actingAs($b, 'api')->getJson("/api/tutor/conversations/$id")->assertNotFound();
        $this->actingAs($b, 'api')->deleteJson("/api/tutor/conversations/$id")->assertNotFound();
        $this->actingAs($b, 'api')->postJson("/api/tutor/courses/{$course->getKey()}/messages", ['text' => 'Continue', 'conversationId' => $id])->assertNotFound();
        $this->actingAs($b, 'api')->getJson("/api/tutor/courses/{$course->getKey()}/conversations")->assertOk()->assertJsonPath('data', []);
        $this->assertNotNull(Conversation::query()->find($id), 'the other learner did not delete it');
        $this->actingAs($a, 'api')->getJson("/api/tutor/conversations/$id")->assertOk();
    }

    public function testALearnerOutsideTheCourseGetsNoTutor(): void
    {
        $course = $this->course();

        $this->actingAs($this->student(), 'api')->getJson("/api/tutor/courses/{$course->getKey()}/status")->assertForbidden();
        $this->actingAs($this->student(), 'api')->postJson("/api/tutor/courses/{$course->getKey()}/messages", ['text' => 'Hello'])->assertForbidden();
    }

    public function testAnAuthorOfOneCourseGetsNoTutorAnalyticsOfAnother(): void
    {
        $mine = $this->course();
        $theirs = $this->course();
        $author = $this->tutor();
        $mine->authors()->attach($author->getKey());

        $this->actingAs($author, 'api')->getJson("/api/admin/tutor/courses/{$mine->getKey()}/analytics")->assertOk();
        $this->actingAs($author, 'api')->getJson("/api/admin/tutor/courses/{$theirs->getKey()}/analytics")->assertForbidden();
        $this->actingAs($this->student(), 'api')->getJson("/api/admin/tutor/courses/{$mine->getKey()}/analytics")->assertForbidden();
    }
}
