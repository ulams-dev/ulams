<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;

/**
 * Course-as-code (ADR 0160): export a course as a Blueprint v3 document, edit it, import it as a proposed
 * version, review the diff, approve and apply, with AI off. Every push is a proposal; agents never approve.
 */
class CourseAsCodeTest extends TestCase
{
    private const P = '/api/admin/course-as-code';

    private function applied(): Session
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->disableAi();

        return $session;
    }

    private function disableAi(): void
    {
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
    }

    private function exported(Session $session): array
    {
        return $this->actingAs($session->author, 'api')->getJson(self::P . "/courses/{$session->course_id}/blueprint")->assertOk()->json('data');
    }

    private function push(Session $session, array $document, string $base, array $headers = [], ?string $as = null)
    {
        return $this->withHeaders($headers)->actingAs($session->author, 'api')
            ->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $document, 'baseVersionId' => $base, 'message' => 'Edit from Git']);
    }

    public function testExportReturnsTheCurrentBlueprintAsV3WithoutAi(): void
    {
        $session = $this->applied();
        // the builder itself is off, course-as-code is not
        $this->actingAs($session->author, 'api')->getJson('/api/admin/course-builder/sessions')->assertStatus(503);

        $data = $this->exported($session);
        $this->assertSame($session->course_id, $data['courseId']);
        $this->assertSame($session->id, $data['sessionId']);
        $this->assertSame($session->current_version_id, $data['versionId']);
        $this->assertSame($session->applied_version_id, $data['appliedVersionId']);
        $this->assertSame(3, $data['schemaVersion']);
        $this->assertSame(3, $data['document']['schemaVersion']);
        $this->assertSame([], app(\Ulams\CourseBuilder\Blueprint\SchemaRegistry::class)->validateBlueprint($data['document']));
        $this->assertSame([], $data['drift']);
    }

    public function testExportRefusesCoursesWithoutABuilderSession(): void
    {
        $author = $this->author();
        $course = Course::factory()->create();
        $this->actingAs($author, 'api')->getJson(self::P . "/courses/{$course->id}/blueprint")->assertStatus(409)->assertJsonPath('code', 'NOT_ADOPTED');
        $this->actingAs($author, 'api')->getJson(self::P . '/courses/99999999/blueprint')->assertStatus(404);
        $this->actingAs($author, 'api')->getJson(self::P . '/courses/abc/blueprint')->assertStatus(404);
    }

    public function testRoundTripExportEditImportGivesAReviewableDiffAndNothingChangesUntilApproved(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $doc = $data['document'];
        $lessonId = $doc['modules'][0]['lessons'][0]['id'];
        $topicId = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lessonId)->where('entity_type', 'topic')->value('entity_id');
        $before = Topic::query()->find($topicId)->updated_at;
        $doc['modules'][0]['lessons'][0]['blocks'][0]['markdown'] .= "\n\nA sentence added in the repository.";
        $doc['modules'][0]['lessons'][0]['title'] = 'A retitled lesson';

        $res = $this->push($session, $doc, $data['versionId'], ['X-Ulams-Commit' => 'abc123', 'X-Ulams-Change-Url' => 'https://git.example/pr/7'])->assertCreated();
        $res->assertJsonPath('data.unchanged', false)->assertJsonPath('data.version.kind', 'import')->assertJsonPath('data.version.status', 'proposed')
            ->assertJsonPath('data.version.parentId', $data['versionId']);
        $changed = collect($res->json('data.changes'))->keyBy('id');
        $this->assertSame('changed', $changed[$lessonId]['kind']);
        $this->assertSame(['title'], array_column($changed[$lessonId]['fields'], 'field'));
        $blockId = $doc['modules'][0]['lessons'][0]['blocks'][0]['id'];
        $this->assertSame('changed', $changed[$blockId]['kind']);
        $this->assertEquals(['changed' => 2, 'added' => 0, 'removed' => 0], $res->json('data.counts'));
        // AI-written text edited by hand is flagged for the reviewer
        $this->assertContains('Lesson 1.1', $res->json('data.handEdited'));

        // a proposal only: the course, the current version and the entity are untouched
        $version = Version::query()->findOrFail($res->json('data.version.id'));
        $this->assertSame('import', $version->kind);
        $this->assertSame('author', $version->origin);
        $this->assertSame(Version::PROPOSED, $version->status);
        $this->assertSame('Edit from Git', $version->reason);
        $this->assertSame(3, $version->document['schemaVersion']);
        $this->assertSame($data['versionId'], $session->refresh()->current_version_id);
        $this->assertTrue($before->equalTo(Topic::query()->find($topicId)->updated_at));

        // the same diff through the diff endpoint, and the import is recorded in the session log
        $diff = $this->actingAs($session->author, 'api')->getJson(self::P . "/versions/{$version->id}/diff")->assertOk()->json('data');
        $this->assertSame(2, count($diff['changes']));
        $event = \Ulams\CourseBuilder\Models\Event::query()->where('session_id', $session->id)->where('type', 'CUSTOM')->get()
            ->first(fn ($e) => ($e->payload['name'] ?? null) === 'course_as_code.imported');
        $this->assertSame('abc123', $event->payload['value']['commit']);
        $this->assertSame('https://git.example/pr/7', $event->payload['value']['changeUrl']);
        $this->assertSame($session->author_id, $event->payload['value']['actorId']);

        // approve, then apply: the learner-facing topic changes only now
        $this->actingAs($session->author, 'api')->postJson(self::P . "/versions/{$version->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame($version->id, $session->refresh()->current_version_id);
        $this->actingAs($session->author, 'api')->postJson(self::P . "/sessions/{$session->id}/apply")->assertStatus(202);
        $this->assertStringContainsString('added in the repository', json_encode(Topic::query()->find($topicId)->topicable->toArray()));
        $this->assertSame($version->id, $session->refresh()->applied_version_id);

        // the next export is the imported document and the same push is now a no-op
        $again = $this->exported($session);
        $this->assertSame($version->id, $again['versionId']);
        $this->push($session, $again['document'], $again['versionId'])->assertOk()->assertJsonPath('data.unchanged', true)->assertJsonPath('data.version', null);
        $this->assertSame(1, Version::query()->where('session_id', $session->id)->where('kind', 'import')->count());
    }

    public function testAnUnchangedOrMetaOnlyPushCreatesNoVersion(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $count = Version::query()->where('session_id', $session->id)->count();
        $this->push($session, $data['document'], $data['versionId'])->assertOk()->assertJsonPath('data.unchanged', true);
        $doc = $data['document'];
        $doc['modules'][0]['meta'] = ['createdBy' => 'git', 'updatedAt' => '2026-10-21T10:00:00Z'];
        $this->push($session, $doc, $data['versionId'])->assertOk()->assertJsonPath('data.unchanged', true);
        $this->assertSame($count, Version::query()->where('session_id', $session->id)->count());
    }

    public function testAV1OrV2DocumentIsUpgradedToV3OnImport(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $doc = $data['document'];
        $doc['schemaVersion'] = 2;
        $doc['course']['title'] = 'A v2 document with a new title';
        $res = $this->push($session, $doc, $data['versionId'])->assertCreated();
        $this->assertSame(3, Version::query()->findOrFail($res->json('data.version.id'))->document['schemaVersion']);
    }

    public function testInvalidDocumentsAreRefusedWithReadableErrors(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $count = Version::query()->where('session_id', $session->id)->count();

        // schema: an unknown lesson format and an origin that does not exist
        $doc = $data['document'];
        $doc['modules'][0]['lessons'][0]['contentType'] = 'hologram';
        $res = $this->push($session, $doc, $data['versionId'])->assertStatus(422)->assertJsonPath('code', 'INVALID_BLUEPRINT');
        $this->assertNotEmpty($res->json('data.errors'));

        // untrusted text: HTML and script links never get through
        $doc = $data['document'];
        $doc['modules'][0]['lessons'][0]['blocks'][0]['markdown'] = 'Hello <script>alert(1)</script> [x](javascript:alert(1))';
        $res = $this->push($session, $doc, $data['versionId'])->assertStatus(422)->assertJsonPath('code', 'INVALID_BLUEPRINT');
        $this->assertStringContainsString('contains HTML', implode(' ', $res->json('data.errors')));

        // an AI element without sources, a citation that is no fragment of the session, a duplicated id
        $doc = $data['document'];
        $doc['modules'][0]['lessons'][0]['blocks'][0]['citations'] = [];
        $this->push($session, $doc, $data['versionId'])->assertStatus(422)->assertJsonPath('code', 'INVALID_BLUEPRINT');
        $doc = $data['document'];
        $doc['modules'][0]['lessons'][0]['blocks'][0]['citations'] = ['frg_aaaaaaaaaaaa'];
        $res = $this->push($session, $doc, $data['versionId'])->assertStatus(422);
        $this->assertStringContainsString('not a fragment of the source', implode(' ', $res->json('data.errors')));
        $doc = $data['document'];
        $doc['modules'][0]['lessons'][0]['blocks'][0]['id'] = $doc['modules'][0]['lessons'][0]['id'];
        $res = $this->push($session, $doc, $data['versionId'])->assertStatus(422);
        $this->assertStringContainsString('used by more than one element', implode(' ', $res->json('data.errors')));

        // a lesson format this server cannot apply yet (SCORM arrives with adoption)
        $doc = $data['document'];
        $doc['modules'][0]['lessons'][0]['contentType'] = 'scorm';
        $doc['modules'][0]['lessons'][0]['asset'] = ['path' => 'pack.zip', 'sha256' => str_repeat('a', 64), 'bytes' => 10, 'mediaType' => 'application/zip'];
        $res = $this->push($session, $doc, $data['versionId'])->assertStatus(422);
        $this->assertStringContainsString('cannot be applied by this server yet', implode(' ', $res->json('data.errors')));

        // a missing body and a base that is not a version of this course
        $this->actingAs($session->author, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", [])->assertStatus(422);
        $this->push($session, $data['document'], '01aaaaaaaaaaaaaaaaaaaaaaaa')->assertStatus(422)->assertJsonPath('code', 'BASE_UNKNOWN');

        $this->assertSame($count, Version::query()->where('session_id', $session->id)->count(), 'nothing was stored');
    }

    public function testAnImportThatChangesTheSameElementAsTheServerIsRefusedWithTheConflicts(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        // someone approves a studio edit after the pull
        $doc = $data['document'];
        $doc['course']['title'] = 'Changed in the studio';
        $versions = app(\Ulams\CourseBuilder\Services\VersionService::class);
        $studio = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'studio edit', null, [], $session->author_id);
        $versions->setCurrent($session, $studio);

        $mine = $data['document'];
        $mine['course']['title'] = 'Changed in Git';
        $res = $this->push($session, $mine, $data['versionId'])->assertStatus(409)->assertJsonPath('code', 'BASE_OUTDATED')
            ->assertJsonPath('data.currentVersionId', $studio->id);
        $this->assertSame('Changed in the studio', collect($res->json('data.remote'))->firstWhere('type', 'course')['fields'][0]['after']);
        $this->assertSame($mine['course']['id'], $res->json('data.conflicts.0.elementId'));
        $this->assertSame('edit-edit', $res->json('data.conflicts.0.kind'));
        $this->assertSame(0, Version::query()->where('session_id', $session->id)->where('kind', 'import')->count());
    }

    public function testAuthorBlocksMayHaveNoCitationsAndAiBlocksMust(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $doc = $data['document'];
        $block = &$doc['modules'][0]['lessons'][0]['blocks'][0];
        $block['markdown'] = 'Written by hand, no source.';
        $block['citations'] = [];
        $block['origin'] = 'author';
        unset($block);
        $res = $this->push($session, $doc, $data['versionId'])->assertCreated();
        $this->assertStringContainsString('written by the author, no sources', implode(' ', $res->json('data.warnings')));
        $blockId = $doc['modules'][0]['lessons'][0]['blocks'][0]['id'];
        $fields = collect($res->json('data.changes'))->firstWhere('id', $blockId)['fields'];
        $origin = collect($fields)->firstWhere('field', 'origin');
        $this->assertSame(['ai', 'author'], [$origin['before'], $origin['after']], 'ai to author is visible in the diff');
        // not a hand-edited AI block: it is now the author's own
        $this->assertNotContains('Lesson 1.1 › block 1', $res->json('data.handEdited'));

        // after approval an AI edit of that block (rebuilt without origin) is the AI's again and must cite
        $merged = \Ulams\CourseBuilder\Pipeline\PatchService::merge('block', $doc['modules'][0]['lessons'][0]['blocks'][0], ['kind' => 'paragraph', 'markdown' => 'AI text', 'citations' => ['frg_aaaaaaaaaaaa'], 'objectiveIds' => []]);
        $this->assertArrayNotHasKey('origin', $merged);
        $lesson = \Ulams\CourseBuilder\Pipeline\PatchService::merge('lesson', ['origin' => 'author'] + $doc['modules'][0]['lessons'][0], ['title' => 'T', 'summary' => '', 'minutes' => 5, 'blocks' => []]);
        $this->assertSame('ai', $lesson['origin']);
    }

    public function testApprovalOverHttpAsAnAgentTokenIsRefusedAndTheVersionStaysProposed(): void
    {
        $session = $this->applied();
        $user = $session->author;
        $data = $this->exported($session);
        $doc = $data['document'];
        $doc['course']['title'] = 'Proposed by an agent';
        $issuer = app(PersonalAccessTokenServiceContract::class);
        $agent = $issuer->issue($user, 'agent', ['builder:write'], 7, 'agent', 'claude')->secret;
        $cli = $issuer->issue($user, 'cli', ['builder:write'], 7, 'cli')->secret;

        $this->app['auth']->forgetGuards();
        $id = $this->withHeaders(['Authorization' => "Bearer {$agent}"])->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $doc, 'baseVersionId' => $data['versionId']])
            ->assertCreated()->json('data.version.id');
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => "Bearer {$agent}"])->postJson(self::P . "/versions/{$id}/approve")->assertStatus(403)->assertJsonPath('code', 'APPROVAL_REQUIRES_HUMAN');
        $this->assertSame(Version::PROPOSED, Version::query()->find($id)->status);
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => "Bearer {$cli}", 'X-Ulams-Client' => 'mcp'])->postJson(self::P . "/versions/{$id}/approve")->assertStatus(403);
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => "Bearer {$cli}", 'X-Ulams-Client' => 'cli'])->postJson(self::P . "/versions/{$id}/approve")->assertOk();
        $this->assertSame(Version::APPROVED, Version::query()->find($id)->status);
    }

    public function testAStaleProposalCannotBeApprovedAndOnlyProposedVersionsCan(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $doc = $data['document'];
        $doc['course']['title'] = 'First proposal';
        $first = $this->push($session, $doc, $data['versionId'])->assertCreated()->json('data.version.id');
        $doc['course']['title'] = 'Second proposal';
        $second = $this->push($session, $doc, $data['versionId'])->assertCreated()->json('data.version.id');

        $this->actingAs($session->author, 'api')->postJson(self::P . "/versions/{$first}/approve")->assertOk();
        // the second proposal was based on the version before the first one was approved
        $this->actingAs($session->author, 'api')->postJson(self::P . "/versions/{$first}/approve")->assertStatus(409)->assertJsonPath('code', 'NOT_PROPOSED');
        $this->assertSame(Version::SUPERSEDED, Version::query()->find($second)->status);
        $this->actingAs($session->author, 'api')->postJson(self::P . "/versions/{$second}/approve")->assertStatus(409);
    }

    public function testPublishWorksWithAiOff(): void
    {
        $session = $this->applied();
        $this->actingAs($session->author, 'api')->postJson(self::P . "/sessions/{$session->id}/publish", ['acknowledgedWarnings' => true])->assertStatus(200)->assertJsonPath('data.published', true);
    }
}
