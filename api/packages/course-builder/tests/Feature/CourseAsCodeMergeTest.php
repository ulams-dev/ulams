<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * Course-as-code, milestone CAC-4 (ADR 0160, plan 7.4): a push that is behind the server is merged element by element
 * (disjoint changes become one proposal on top of the current version, overlapping changes refuse with the conflicts
 * and store nothing). AI is off throughout.
 */
class CourseAsCodeMergeTest extends TestCase
{
    private const P = '/api/admin/course-as-code';

    private function applied(): Session
    {
        $session = $this->toApplied($this->author());
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);

        return $session;
    }

    private function exported(Session $session): array
    {
        return $this->actingAs($session->author, 'api')->getJson(self::P . "/courses/{$session->course_id}/blueprint")->assertOk()->json('data');
    }

    private function push(Session $session, array $document, string $base)
    {
        return $this->actingAs($session->author, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $document, 'baseVersionId' => $base, 'message' => 'From Git']);
    }

    /** A person approves an edit made in the studio: an approved version on top of the current one. */
    private function studioEdit(Session $session, callable $edit): Version
    {
        $doc = $session->refresh()->currentVersion->document;
        $edit($doc);
        $versions = app(VersionService::class);
        $version = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'studio edit', null, [], $session->author_id);
        $versions->setCurrent($session, $version);

        return $version;
    }

    /* ------------------------------------------------------------ the server-side merge */

    public function testDisjointChangesAreMergedOnTopOfTheCurrentVersion(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $studio = $this->studioEdit($session, function (array &$doc) {
            $doc['modules'][1]['lessons'][0]['title'] = 'Retitled in the studio';
        });

        $mine = $data['document'];
        $mine['modules'][0]['lessons'][0]['blocks'][0]['markdown'] .= "\n\nAdded in Git.";
        $res = $this->push($session, $mine, $data['versionId'])->assertCreated();

        $res->assertJsonPath('data.version.kind', 'import')->assertJsonPath('data.version.status', 'proposed')->assertJsonPath('data.version.parentId', $studio->id);
        $this->assertSame($data['versionId'], $res->json('data.merged.baseVersionId'));
        $this->assertSame($studio->id, $res->json('data.merged.currentVersionId'));
        $this->assertSame([['id' => $mine['modules'][0]['lessons'][0]['blocks'][0]['id'], 'kind' => 'changed']], $res->json('data.merged.fromPush'));
        $this->assertSame([['id' => $mine['modules'][1]['lessons'][0]['id'], 'kind' => 'changed']], $res->json('data.merged.fromServer'));
        // the diff is against the current version: only what the push adds on top of the studio edit
        $this->assertEquals(['changed' => 1, 'added' => 0, 'removed' => 0], $res->json('data.counts'));

        $stored = Version::query()->findOrFail($res->json('data.version.id'));
        $this->assertSame('Retitled in the studio', $stored->document['modules'][1]['lessons'][0]['title']);
        $this->assertStringContainsString('Added in Git.', $stored->document['modules'][0]['lessons'][0]['blocks'][0]['markdown']);
        $this->assertSame('From Git', $stored->reason);
        // the sources stay the server's
        $this->assertSame($studio->document['sources'], $stored->document['sources']);
        $event = Event::query()->where('session_id', $session->id)->where('type', 'CUSTOM')->get()->first(fn ($e) => ($e->payload['name'] ?? null) === 'course_as_code.imported');
        $this->assertSame($studio->id, $event->payload['value']['mergedWith']);

        // it can be approved because its parent is the current version
        $this->actingAs($session->author, 'api')->postJson(self::P . "/versions/{$stored->id}/approve")->assertOk();
        $this->assertSame($stored->id, $session->refresh()->current_version_id);
    }

    public function testAnAddedLessonAndAnAdminReorderMergeAndTheReorderIsKept(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $this->studioEdit($session, function (array &$doc) {
            $doc['modules'] = array_reverse($doc['modules']);
        });
        $mine = $data['document'];
        $fresh = $mine['modules'][0]['lessons'][0];
        $n = 0;
        $reid = function (array $node) use (&$reid, &$n): array {
            foreach ($node as $key => $value) {
                if ($key === 'id') {
                    $node[$key] = '01zzzzzzzzzzzzzzzzzzzzz' . str_pad((string) ++$n, 3, '0', STR_PAD_LEFT);
                } elseif (is_array($value)) {
                    $node[$key] = $reid($value);
                }
            }

            return $node;
        };
        $fresh = $reid($fresh);
        $fresh['title'] = 'New lesson';
        $fresh['quiz'] = null;
        foreach ($fresh['blocks'] as $i => $b) {
            $fresh['blocks'][$i]['objectiveIds'] = [$fresh['objectives'][0]['id']];
        }
        $mine['modules'][0]['lessons'][] = $fresh;
        $res = $this->push($session, $mine, $data['versionId'])->assertCreated();
        $merged = Version::query()->findOrFail($res->json('data.version.id'))->document;
        $this->assertSame(array_reverse(array_column($data['document']['modules'], 'id')), array_column($merged['modules'], 'id'), "the studio's order stands");
        $last = collect($merged['modules'])->firstWhere('id', $mine['modules'][0]['id']);
        $this->assertSame($fresh['id'], end($last['lessons'])['id']);
    }

    public function testOverlappingChangesAreRefusedWithTheConflictsAndNothingIsStored(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $blockId = $data['document']['modules'][0]['lessons'][0]['blocks'][0]['id'];
        $studio = $this->studioEdit($session, function (array &$doc) {
            $doc['modules'][0]['lessons'][0]['blocks'][0]['markdown'] = 'The studio wording.';
        });
        $count = Version::query()->where('session_id', $session->id)->count();

        $mine = $data['document'];
        $mine['modules'][0]['lessons'][0]['blocks'][0]['markdown'] = 'The Git wording.';
        $mine['modules'][1]['lessons'][0]['title'] = 'Also retitled in Git';
        $res = $this->push($session, $mine, $data['versionId'])->assertStatus(409)->assertJsonPath('code', 'BASE_OUTDATED');

        $res->assertJsonPath('data.currentVersionId', $studio->id)->assertJsonPath('data.currentVersionNumber', $studio->number);
        $this->assertCount(1, $res->json('data.conflicts'), 'only the element both sides changed');
        $conflict = $res->json('data.conflicts.0');
        $this->assertSame($blockId, $conflict['elementId']);
        $this->assertSame('edit-edit', $conflict['kind']);
        $this->assertSame('block', $conflict['type']);
        $this->assertSame('Lesson 1.1 › block 1', $conflict['label']);
        $this->assertSame('markdown', $conflict['field']);
        // ours is the pushed value, theirs is the server's
        $this->assertSame(['field' => 'markdown', 'base' => $data['document']['modules'][0]['lessons'][0]['blocks'][0]['markdown'], 'ours' => 'The Git wording.', 'theirs' => 'The studio wording.'], $conflict['fields'][0]);
        $this->assertSame($count, Version::query()->where('session_id', $session->id)->count(), 'nothing was stored');
    }

    public function testDeletingWhatTheServerEditedIsAConflictAndDeletingWhatItLeftAloneIsNot(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $this->studioEdit($session, function (array &$doc) {
            $doc['modules'][0]['lessons'][0]['blocks'][1]['markdown'] = 'Edited in the studio.';
        });

        $mine = $data['document'];
        array_splice($mine['modules'][0]['lessons'][0]['blocks'], 1, 1);
        $res = $this->push($session, $mine, $data['versionId'])->assertStatus(409);
        $this->assertSame('removed-ours', $res->json('data.conflicts.0.kind'));

        $mine = $data['document'];
        array_splice($mine['modules'][0]['lessons'][0]['blocks'], 0, 1);
        $res = $this->push($session, $mine, $data['versionId'])->assertCreated();
        $merged = Version::query()->findOrFail($res->json('data.version.id'))->document;
        $this->assertCount(1, $merged['modules'][0]['lessons'][0]['blocks']);
        $this->assertSame('Edited in the studio.', $merged['modules'][0]['lessons'][0]['blocks'][0]['markdown']);
    }

    public function testAPushWhoseChangesTheServerAlreadyHasIsUnchanged(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $this->studioEdit($session, function (array &$doc) {
            $doc['course']['title'] = 'The same title';
        });
        $mine = $data['document'];
        $mine['course']['title'] = 'The same title';
        $count = Version::query()->where('session_id', $session->id)->count();
        $this->push($session, $mine, $data['versionId'])->assertOk()->assertJsonPath('data.unchanged', true)->assertJsonPath('data.version', null)
            ->assertJsonPath('data.merged.baseVersionId', $data['versionId']);
        $this->assertSame($count, Version::query()->where('session_id', $session->id)->count());
    }

    public function testAMergedDocumentIsValidatedLikeAnyOther(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $this->studioEdit($session, function (array &$doc) {
            $doc['course']['title'] = 'Studio title';
        });
        $mine = $data['document'];
        $mine['modules'][0]['lessons'][0]['blocks'][0]['markdown'] = 'Hello <script>alert(1)</script>';
        $this->push($session, $mine, $data['versionId'])->assertStatus(422)->assertJsonPath('code', 'INVALID_BLUEPRINT');
    }

    public function testAnAgentTokenMayProposeAMergedPushButNeverApproveIt(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $this->studioEdit($session, function (array &$doc) {
            $doc['course']['title'] = 'Studio title';
        });
        $mine = $data['document'];
        $mine['modules'][0]['lessons'][0]['title'] = 'Agent title';
        $agent = app(PersonalAccessTokenServiceContract::class)->issue($session->author, 'agent', ['builder:write'], 7, 'agent', 'claude')->secret;
        $this->app['auth']->forgetGuards();
        $id = $this->withHeaders(['Authorization' => "Bearer {$agent}"])->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $mine, 'baseVersionId' => $data['versionId']])->assertCreated()->json('data.version.id');
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => "Bearer {$agent}"])->postJson(self::P . "/versions/{$id}/approve")->assertStatus(403)->assertJsonPath('code', 'APPROVAL_REQUIRES_HUMAN');
        $this->assertSame(Version::PROPOSED, Version::query()->find($id)->status);
    }

    public function testAVersionCanBeReadWithItsDocumentForTheCliMerge(): void
    {
        $session = $this->applied();
        $data = $this->exported($session);
        $res = $this->actingAs($session->author, 'api')->getJson(self::P . "/versions/{$data['versionId']}")->assertOk();
        $this->assertSame($data['versionId'], $res->json('data.id'));
        $this->assertSame($data['document'], $res->json('data.document'));
        $this->assertSame($data['versionNumber'], $res->json('data.number'));
    }
}
