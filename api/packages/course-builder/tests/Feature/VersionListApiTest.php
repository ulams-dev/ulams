<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * ADR 0192, CV-3: GET api/admin/course-versions/courses/{course}/versions lists every version of a course's branch, the
 * ones that were never released too. No AI gate, no model call, read-only. The cross-tenant walk of every route is
 * TenantIsolationCoverageTest; this file checks the list against the other tenant's token, other authors and unknown ids.
 */
class VersionListApiTest extends TestCase
{
    private const API = '/api/admin/course-versions';

    private function url(Session|int $course, string $query = ''): string
    {
        return self::API . '/courses/' . ($course instanceof Session ? $course->course_id : $course) . '/versions' . $query;
    }

    private function version(Session $session, callable $edit, string $status = Version::APPROVED, ?string $variantId = null): Version
    {
        $parent = $session->refresh()->currentVersion;

        return app(VersionService::class)->create($session, $edit($parent->document), 'author', 'author', $status, $parent, 'edit', null, [], $session->author_id, null, $variantId);
    }

    public function testEveryVersionIsListedNewestFirstWithItsReleaseWhenItHasOne(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $released = Release::query()->where('session_id', $session->id)->first();
        $proposal = $this->version($session, function (array $doc) {
            $doc['modules'][0]['lessons'][0]['title'] = 'Proposed only';

            return $doc;
        }, Version::PROPOSED);

        $data = $this->actingAs($author, 'api')->getJson($this->url($session))->assertOk()->json('data');

        $this->assertSame($session->id, $data['sessionId']);
        $this->assertSame((int) $session->course_id, $data['courseId']);
        $this->assertSame((int) $session->course_id, $data['baseCourseId']);
        $this->assertNull($data['variantId']);
        $this->assertSame($proposal->id, $data['versions'][0]['id'], 'newest first');
        $this->assertSame('proposed', $data['versions'][0]['status']);
        $this->assertNull($data['versions'][0]['releaseId']);
        $row = collect($data['versions'])->firstWhere('id', $released->version_id);
        $this->assertSame($released->id, $row['releaseId']);
        $this->assertSame(1, $row['releaseNumber']);
        $this->assertGreaterThanOrEqual(2, $data['total']);
        $this->assertArrayNotHasKey('document', $row);
        $this->assertSame([], $data['variants']);
    }

    public function testPagingIsBounded(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $total = Version::query()->where('session_id', $session->id)->count();

        $page = $this->actingAs($author, 'api')->getJson($this->url($session, '?perPage=1&page=2'))->assertOk();
        $this->assertCount(1, $page->json('data.versions'));
        $page->assertJsonPath('data.total', $total)->assertJsonPath('data.page', 2)->assertJsonPath('data.perPage', 1);
        $this->actingAs($author, 'api')->getJson($this->url($session, '?perPage=5000'))->assertOk()->assertJsonPath('data.perPage', 200);
    }

    public function testAVariantCourseListsItsOwnBranchAndBranchAllListsEveryBranchWithTheVariants(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $variant = Variant::query()->create(['session_id' => $session->id, 'key' => 'expert', 'label' => 'Expert', 'level' => 'expert', 'audience_brief' => ['level' => 'expert'], 'status' => Variant::APPROVED, 'course_id' => 987654]);
        $branch = $this->version($session, function (array $doc) {
            $doc['modules'][0]['lessons'][0]['title'] = 'Expert title';

            return $doc;
        }, Version::APPROVED, $variant->id);

        $own = $this->actingAs($author, 'api')->getJson(self::API . '/courses/987654/versions')->assertOk()->json('data');
        $this->assertSame([$branch->id], array_column($own['versions'], 'id'));
        $this->assertSame($variant->id, $own['variantId']);
        $this->assertSame((int) $session->course_id, $own['baseCourseId']);
        $this->assertSame([['id' => $variant->id, 'key' => 'expert', 'label' => 'Expert', 'level' => 'expert', 'role' => null, 'status' => 'approved', 'courseId' => 987654]], $own['variants']);

        $base = $this->actingAs($author, 'api')->getJson($this->url($session))->assertOk()->json('data');
        $this->assertNotContains($branch->id, array_column($base['versions'], 'id'), 'the base branch does not list a variant version');
        $this->assertSame([$variant->id], array_column($base['variants'], 'id'));

        $all = $this->actingAs($author, 'api')->getJson($this->url($session, '?branch=all'))->assertOk()->json('data');
        $this->assertContains($branch->id, array_column($all['versions'], 'id'));
        $this->assertContains($session->applied_version_id, array_column($all['versions'], 'id'));
        $this->assertSame($variant->id, collect($all['versions'])->firstWhere('id', $branch->id)['variantId']);
    }

    public function testItWorksWithAiSwitchedOffAndMakesNoModelCall(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        $calls = DB::table('ai_calls')->count();

        $this->actingAs($author, 'api')->getJson($this->url($session))->assertOk();

        $this->assertSame($calls, DB::table('ai_calls')->count());
    }

    public function testAnAdminCanListAnyCourse(): void
    {
        $session = $this->toApplied($this->author());

        $this->actingAs($this->admin(), 'api')->getJson($this->url($session))->assertOk();
    }

    public function testAnotherAuthorAStudentAndAGuestAreRefused(): void
    {
        $session = $this->toApplied($this->author());

        foreach ([$this->tutor(), $this->student()] as $user) {
            $this->actingAs($user, 'api')->getJson($this->url($session))->assertStatus(403);
        }
        $this->app['auth']->forgetGuards();
        $this->getJson($this->url($session))->assertStatus(401);
    }

    public function testUnknownCoursesAreNotFoundAndAnotherAuthorsVersionsNeverShow(): void
    {
        $author = $this->author();
        $mine = $this->toApplied($author);
        $theirs = $this->toApplied($this->author());

        $this->actingAs($author, 'api')->getJson($this->url(999999))->assertStatus(404)->assertJsonFragment(['message' => 'This course was not made with the Course Builder, so it has no versions. Adopt the course to track its versions.']);
        $this->actingAs($author, 'api')->getJson(self::API . '/courses/abc/versions')->assertStatus(404);
        $this->actingAs($author, 'api')->getJson($this->url($theirs))->assertStatus(403);
        $ids = array_column($this->actingAs($author, 'api')->getJson($this->url($mine, '?branch=all'))->json('data.versions'), 'id');
        $this->assertNotContains($theirs->applied_version_id, $ids);
    }

    public function testATokenOfAnotherTenantIsRejected(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $token = $author->createToken('test')->accessToken;
        $call = function () use ($token, $session) {
            $res = $this->json('GET', $this->url($session), [], ['Authorization' => 'Bearer ' . $token]);
            Auth::forgetGuards();

            return $res;
        };
        $call()->assertOk();

        // another tenant has its own Passport key pair (ADR 0007): the same token no longer verifies
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();

        $call()->assertUnauthorized();
    }
}
