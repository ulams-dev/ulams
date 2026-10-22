<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\CourseBuilder\Contracts\SourceHistory;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionComparer;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * ADR 0192, CV-2: GET api/admin/course-versions/courses/{course}/compare, the semantic diff of any two versions or releases
 * of one session. No AI gate, no model call. The cross-tenant walk of every route is TenantIsolationCoverageTest; this file
 * checks the compare route against the other tenant's token, other authors, other sessions and unknown ids.
 */
class CompareApiTest extends TestCase
{
    private const API = '/api/admin/course-versions';

    /** @return Release[] oldest first */
    private function releases(Session $session): array
    {
        return Release::query()->where('session_id', $session->id)->orderBy('number')->get()->all();
    }

    private function rename(Session $session, string $title): void
    {
        $lesson = $session->refresh()->currentVersion->document['modules'][0]['lessons'][0];
        $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/outline", ['action' => 'rename', 'id' => $lesson['id'], 'title' => $title])->assertOk();
    }

    private function url(Session|int $course, string $from, string $to): string
    {
        return self::API . '/courses/' . ($course instanceof Session ? $course->course_id : $course) . "/compare?from={$from}&to={$to}";
    }

    /** A new approved version of the session's document, edited by $edit. */
    private function version(Session $session, callable $edit, ?string $variantId = null): Version
    {
        $parent = $session->refresh()->currentVersion;

        return app(VersionService::class)->create($session, $edit($parent->document), 'author', 'author', Version::APPROVED, $parent, 'edit', null, [], $session->author_id, null, $variantId);
    }

    public function testTwoReleasesAreComparedWithTheirSummary(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->rename($session, 'A better title');
        [$one, $two] = $this->releases($session);

        $response = $this->actingAs($author, 'api')->getJson($this->url($session, $one->id, $two->id))->assertOk();

        $response->assertJsonPath('data.diffVersion', 1)->assertJsonPath('data.courseId', (int) $session->course_id)->assertJsonPath('data.sessionId', $session->id)
            ->assertJsonPath('data.from.releaseNumber', 1)->assertJsonPath('data.to.releaseNumber', 2)->assertJsonPath('data.from.versionId', $one->version_id)
            ->assertJsonPath('data.summary.counts.changed', 1)->assertJsonPath('data.summary.types.lesson.changed', 1);
        $lesson = collect($response->json('data.changes'))->firstWhere('type', 'lesson');
        $this->assertSame('changed', $lesson['kind']);
        $this->assertSame('title', $lesson['fields'][0]['field']);
        $this->assertSame('A better title', $lesson['fields'][0]['after']);
        $this->assertStringContainsString('1 lesson changed', $response->json('data.summary.text'));
    }

    public function testVersionsAndReleasesCanBeMixedAndSwapped(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->rename($session, 'Renamed');
        [$one, $two] = $this->releases($session);

        $forward = $this->actingAs($author, 'api')->getJson($this->url($session, $one->version_id, $two->id))->assertOk();
        $back = $this->actingAs($author, 'api')->getJson($this->url($session, $two->version_id, $one->id))->assertOk();

        $forward->assertJsonPath('data.from.versionId', $one->version_id)->assertJsonPath('data.from.releaseNumber', 1);
        $this->assertSame('Renamed', collect($forward->json('data.changes'))->firstWhere('type', 'lesson')['fields'][0]['after']);
        $this->assertSame('Renamed', collect($back->json('data.changes'))->firstWhere('type', 'lesson')['fields'][0]['before']);
        $same = $this->actingAs($author, 'api')->getJson($this->url($session, $one->id, $one->id))->assertOk();
        $this->assertSame([], $same->json('data.changes'));
        $this->assertSame('No change.', $same->json('data.summary.text'));
    }

    public function testAVersionThatWasNeverReleasedCanBeComparedToo(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $first = $session->applied_version_id;
        $proposal = $this->version($session, function (array $doc) {
            $doc['modules'][0]['lessons'][0]['summary'] = 'A proposal nobody applied';

            return $doc;
        });

        $response = $this->actingAs($author, 'api')->getJson($this->url($session, $first, $proposal->id))->assertOk();

        $response->assertJsonPath('data.to.releaseId', null)->assertJsonPath('data.to.versionNumber', $proposal->number)->assertJsonPath('data.to.status', Version::APPROVED);
        $this->assertSame(['summary'], array_column(collect($response->json('data.changes'))->firstWhere('type', 'lesson')['fields'], 'field'));
    }

    public function testMovedElementsAndQuizAnswerKeysComeThrough(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author, assessments: ['quiz', 'final']);
        $first = $session->applied_version_id;
        $changed = $this->version($session, function (array $doc) {
            [$moved] = array_splice($doc['modules'][0]['lessons'], 0, 1);
            $doc['modules'][1]['lessons'][] = $moved;
            foreach ($doc['modules'] as &$module) {
                foreach ($module['lessons'] as &$lesson) {
                    foreach ($lesson['quiz']['questions'] ?? [] as $q => $question) {
                        if (isset($done)) {
                            break 3;
                        }
                        foreach ($question['options'] as $o => $option) {
                            $lesson['quiz']['questions'][$q]['options'][$o]['correct'] = !$option['correct'];
                        }
                        $done = true;
                    }
                }
            }

            return $doc;
        });

        $data = $this->actingAs($author, 'api')->getJson($this->url($session, $first, $changed->id))->assertOk()->json('data');

        $this->assertGreaterThanOrEqual(1, $data['summary']['counts']['moved']);
        $this->assertSame(1, $data['summary']['types']['lesson']['moved']);
        $this->assertSame(1, $data['summary']['quiz']['answerKeysChanged']);
        $this->assertTrue($data['quiz'][0]['answerKeyChanged']);
        $this->assertNotNull($data['quiz'][0]['consequence']);
        $this->assertContains('lesson', array_column(array_filter($data['changes'], fn ($c) => $c['moved'] !== null), 'type'));
    }

    public function testAChangedCitationBringsTheSourcePassages(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $first = $session->applied_version_id;
        $old = $session->currentVersion->document['modules'][0]['lessons'][0]['citations'];
        $other = Fragment::query()->whereNotIn('id', $old)->firstOrFail();
        $changed = $this->version($session, function (array $doc) use ($other) {
            $doc['modules'][0]['lessons'][0]['citations'] = [$other->id];

            return $doc;
        });

        $data = $this->actingAs($author, 'api')->getJson($this->url($session, $first, $changed->id))->assertOk()->json('data');

        $lesson = collect($data['changes'])->firstWhere('type', 'lesson');
        $this->assertSame([$other->id], $lesson['citations']['added']);
        $this->assertEqualsCanonicalizing($old, $lesson['citations']['removed']);
        $this->assertArrayHasKey($other->id, $data['fragments']);
        $this->assertSame($other->text === '' ? '' : mb_substr($other->text, 0, 600), $data['fragments'][$other->id]['text']);
        $this->assertNotSame('', $data['fragments'][$old[0]]['label']);
        $this->assertSame(1, $data['summary']['citations']['added']);
    }

    public function testTheSourceHistoryRepointsCitationsAndSuppliesThePassagesOfTheirRevisions(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $first = $session->applied_version_id;
        $old = $session->currentVersion->document['modules'][0]['lessons'][0]['citations'][0];
        $other = Fragment::query()->where('id', '!=', $old)->firstOrFail();
        // Living Course answers this in the full app (its own test covers the real tables); here a fake history shows the plumbing
        $this->app->instance(SourceHistory::class, new class ($old, $other->id) implements SourceHistory {
            public function __construct(private string $old, private string $new)
            {
            }

            public function repointed(array $fragmentIds): array
            {
                return in_array($this->old, $fragmentIds, true) && in_array($this->new, $fragmentIds, true) ? [$this->old => $this->new] : [];
            }

            public function passage(string $fragmentId, array $revisionIds): ?array
            {
                return match ($revisionIds) {
                    ['rev-1'] => ['label' => '§2 Brewing', 'text' => 'The passage in revision one', 'revisionId' => 'rev-1', 'revisionNumber' => 1],
                    ['rev-2'] => ['label' => '§3 Brewing ratios', 'text' => 'The passage in revision two', 'revisionId' => 'rev-2', 'revisionNumber' => 2],
                    default => null,
                };
            }
        });
        $this->app->forgetInstance(VersionComparer::class);
        $this->app->forgetInstance(\Ulams\CourseBuilder\Http\Controllers\CompareController::class);
        Version::query()->findOrFail($first)->forceFill(['source_revisions' => ['src' => 'rev-1']])->save();
        $changed = $this->version($session, function (array $doc) use ($other) {
            $doc['modules'][0]['lessons'][0]['citations'] = [$other->id];

            return $doc;
        });
        $changed->forceFill(['source_revisions' => ['src' => 'rev-2']])->save();

        $data = $this->actingAs($author, 'api')->getJson($this->url($session, $first, $changed->id))->assertOk()->json('data');

        $lesson = collect($data['changes'])->firstWhere('type', 'lesson');
        $this->assertSame([['from' => $old, 'to' => $other->id]], $lesson['citations']['repointed']);
        $this->assertSame([[], [], 1], [$lesson['citations']['added'], $lesson['citations']['removed'], $data['summary']['citations']['repointed']]);
        $this->assertSame(['label' => '§2 Brewing', 'text' => 'The passage in revision one', 'sourceRevision' => ['id' => 'rev-1', 'number' => 1]], $data['fragments'][$old]);
        $this->assertSame(['label' => '§3 Brewing ratios', 'text' => 'The passage in revision two', 'sourceRevision' => ['id' => 'rev-2', 'number' => 2]], $data['fragments'][$other->id]);
    }

    public function testThePayloadIsCachedPerPair(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->rename($session, 'Cached');
        [$one, $two] = $this->releases($session);
        $key = 'course-builder:compare:v1:' . $one->version_id . ':' . $two->version_id;
        Cache::forget($key);

        $first = $this->actingAs($author, 'api')->getJson($this->url($session, $one->id, $two->id))->assertOk()->json('data');

        $this->assertTrue(Cache::has($key));
        $this->assertSame($first, $this->actingAs($author, 'api')->getJson($this->url($session, $one->id, $two->id))->assertOk()->json('data'));
        $this->assertSame($first['summary'], app(VersionComparer::class)->compare(Version::query()->findOrFail($one->version_id), Version::query()->findOrFail($two->version_id))['summary']);
    }

    public function testItWorksWithAiSwitchedOffAndMakesNoModelCall(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->rename($session, 'No model needed');
        [$one, $two] = $this->releases($session);
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        $calls = DB::table('ai_calls')->count();

        $this->actingAs($author, 'api')->getJson($this->url($session, $one->id, $two->id))->assertOk()->assertJsonPath('data.summary.counts.changed', 1);

        $this->assertSame($calls, DB::table('ai_calls')->count());
    }

    public function testAVariantIsComparedWithItsBaseThroughEitherCourse(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $base = $session->applied_version_id;
        $variant = Variant::query()->create(['session_id' => $session->id, 'key' => 'expert', 'label' => 'Expert', 'level' => 'expert', 'audience_brief' => ['level' => 'expert'], 'status' => Variant::APPROVED, 'course_id' => 987654]);
        $version = $this->version($session, function (array $doc) {
            array_pop($doc['modules'][0]['lessons']);
            $doc['modules'][0]['lessons'][0]['title'] = 'Expert title';

            return $doc;
        }, $variant->id);

        foreach ([(int) $session->course_id, 987654] as $course) {
            $data = $this->actingAs($author, 'api')->getJson($this->url($course, $base, $version->id))->assertOk()->json('data');
            $this->assertSame($variant->id, $data['to']['variantId']);
            $this->assertNull($data['from']['variantId']);
            $this->assertGreaterThanOrEqual(1, $data['summary']['counts']['changed']);
        }
    }

    public function testBothSidesAreRequired(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $version = $session->applied_version_id;

        $this->actingAs($author, 'api')->getJson(self::API . "/courses/{$session->course_id}/compare?from={$version}")->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->actingAs($author, 'api')->getJson(self::API . "/courses/{$session->course_id}/compare")->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
    }

    public function testUnknownIdsAndCoursesAreNotFound(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $version = $session->applied_version_id;
        $missing = '01aaaaaaaaaaaaaaaaaaaaaaaa';

        $this->actingAs($author, 'api')->getJson($this->url($session, $version, $missing))->assertStatus(404);
        $this->actingAs($author, 'api')->getJson($this->url($session, 'not-an-id', $version))->assertStatus(404);
        $this->actingAs($author, 'api')->getJson($this->url(999999, $version, $version))->assertStatus(404)->assertJsonFragment(['message' => 'This course was not made with the Course Builder, so it has no versions. Adopt the course to track its versions.']);
        $this->actingAs($author, 'api')->getJson(self::API . "/courses/abc/compare?from={$version}&to={$version}")->assertStatus(404);
    }

    public function testAPairFromTwoSessionsIsRefused(): void
    {
        $author = $this->author();
        $mine = $this->toApplied($author);
        $other = $this->toApplied($author);
        $mineVersion = $mine->applied_version_id;
        $otherVersion = $other->applied_version_id;
        $otherRelease = $this->releases($other)[0];

        $this->actingAs($author, 'api')->getJson($this->url($mine, $mineVersion, $otherVersion))->assertStatus(404);
        $this->actingAs($author, 'api')->getJson($this->url($mine, $otherVersion, $mineVersion))->assertStatus(404);
        $this->actingAs($author, 'api')->getJson($this->url($mine, $mineVersion, $otherRelease->id))->assertStatus(404);
        $this->actingAs($author, 'api')->getJson($this->url($other, $mineVersion, $otherVersion))->assertStatus(404);
        $this->actingAs($author, 'api')->getJson($this->url($mine, $mineVersion, $mineVersion))->assertOk();
    }

    public function testAnAdminCanCompareAnyCourse(): void
    {
        $session = $this->toApplied($this->author());
        $version = $session->applied_version_id;

        $this->actingAs($this->admin(), 'api')->getJson($this->url($session, $version, $version))->assertOk();
    }

    public function testAnotherAuthorAStudentAndAGuestAreRefused(): void
    {
        $session = $this->toApplied($this->author());
        $version = $session->applied_version_id;
        $url = $this->url($session, $version, $version);

        foreach ([$this->tutor(), $this->student()] as $user) {
            $this->actingAs($user, 'api')->getJson($url)->assertStatus(403);
        }
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertStatus(401);
    }

    public function testATokenOfAnotherTenantIsRejected(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $version = $session->applied_version_id;
        $token = $author->createToken('test')->accessToken;
        $call = function () use ($token, $session, $version) {
            $res = $this->json('GET', $this->url($session, $version, $version), [], ['Authorization' => 'Bearer ' . $token]);
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
