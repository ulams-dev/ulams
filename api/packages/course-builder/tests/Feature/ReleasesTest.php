<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\ReleaseRecorder;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Tests\TestCase;

/** ADR 0192, CV-1: a release is recorded by every successful apply (the API that lists them is ReleaseApiTest). */
class ReleasesTest extends TestCase
{
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

    public function testTheFirstApplyIsReleaseOneWithEverythingAdded(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);

        $releases = $this->releases($session);
        $this->assertCount(1, $releases);
        $release = $releases[0];
        $this->assertSame(1, $release->number);
        $this->assertSame('generate', $release->trigger);
        $this->assertSame($session->applied_version_id, $release->version_id);
        $this->assertSame((int) $session->course_id, $release->course_id);
        $this->assertNull($release->previous_release_id);
        $this->assertNull($release->variant_id);
        $this->assertSame($author->getKey(), $release->approved_by);
        $this->assertTrue($release->summary['first']);
        $this->assertGreaterThan(0, $release->summary['lessons']['added']);
        $this->assertSame(0, $release->summary['lessons']['removed']);
        $this->assertNotNull($release->released_at);
    }

    public function testNothingIsReleasedBeforeTheApply(): void
    {
        $session = $this->toApplyReview($this->author());

        $this->assertSame([], $this->releases($session));
    }

    public function testAnAuthorEditIsTheNextReleaseWithAChangeSummary(): void
    {
        $session = $this->toApplied($this->author());
        $first = $this->releases($session)[0];

        $this->rename($session, 'A better title');

        $releases = $this->releases($session->refresh());
        $this->assertCount(2, $releases);
        $second = $releases[1];
        $this->assertSame([2, 'author', $session->applied_version_id, $first->id], [$second->number, $second->trigger, $second->version_id, $second->previous_release_id]);
        $this->assertFalse($second->summary['first']);
        $this->assertSame(['added' => 0, 'changed' => 1, 'removed' => 0], $second->summary['lessons']);
        $this->assertSame(1, $second->summary['counts']['changed']);
    }

    public function testAnApprovedChatPatchIsAChatRelease(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $question = null;
        foreach ($session->currentVersion->document['modules'] as $module) {
            foreach ($module['lessons'] as $lesson) {
                $question ??= $lesson['quiz']['questions'][0] ?? null;
            }
        }
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'threadId' => $session->id, 'runId' => 'r',
            'messages' => [['id' => 'm1', 'role' => 'user', 'content' => 'Make the distractors less obvious']],
            'forwardedProps' => ['selection' => ['elementId' => $question['id']]],
        ])->assertStatus(202);
        $patch = Version::query()->where('session_id', $session->id)->where('kind', 'patch')->latest('number')->firstOrFail();
        $this->assertCount(1, $this->releases($session), 'a proposal is not a release');

        $this->action($author, $session, 'approve_patch', "patch-{$patch->id}", ['versionId' => $patch->id])->assertStatus(202);

        $releases = $this->releases($session->refresh());
        $this->assertCount(2, $releases);
        $this->assertSame(['chat', $patch->id], [$releases[1]->trigger, $releases[1]->version_id]);
        $this->assertSame(1, $releases[1]->summary['questions']['changed']);
    }

    public function testUndoAndRedoThatReapplyAnOldVersionAreRestoreReleases(): void
    {
        $session = $this->toApplied($this->author());
        $this->rename($session, 'Renamed once');
        $author = $session->author;

        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/undo")->assertOk();
        $releases = $this->releases($session->refresh());
        $this->assertCount(3, $releases);
        $this->assertSame('restore', $releases[2]->trigger);
        $this->assertSame($releases[0]->version_id, $releases[2]->version_id);
        $this->assertSame(1, $releases[2]->summary['lessons']['changed'], 'the rename is reverted');

        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/redo")->assertOk();
        $releases = $this->releases($session->refresh());
        $this->assertCount(4, $releases);
        $this->assertSame(['restore', $releases[1]->version_id], [$releases[3]->trigger, $releases[3]->version_id]);
        $this->assertSame([1, 2, 3, 4], array_map(fn ($r) => $r->number, $releases));
    }

    public function testRestoringAnOldVersionIsARestoreRelease(): void
    {
        $session = $this->toApplied($this->author());
        $first = $session->applied_version_id;
        $this->rename($session, 'Renamed once');

        $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/versions/{$first}/restore")->assertOk();

        $last = array_values(array_slice($this->releases($session->refresh()), -1))[0];
        $this->assertSame(3, $last->number);
        $this->assertSame('restore', $last->trigger);
        $this->assertSame('restore', Version::query()->findOrFail($last->version_id)->kind);
    }

    public function testApplyingTheVersionThatIsAlreadyLiveWritesNothing(): void
    {
        $session = $this->toApplied($this->author());
        $version = Version::query()->findOrFail($session->applied_version_id);

        $this->assertNull(app(ReleaseRecorder::class)->applied($session, $version, (int) $session->course_id));
        $this->assertCount(1, $this->releases($session));
    }

    public function testEveryReleaseAnnouncesItselfOnceAndANoOpApplyDoesNot(): void
    {
        $author = $this->author();
        \Illuminate\Support\Facades\Event::fake([\Ulams\CourseBuilder\Events\CourseVersionApplied::class]);
        $session = $this->toApplied($author);
        \Illuminate\Support\Facades\Event::assertDispatchedTimes(\Ulams\CourseBuilder\Events\CourseVersionApplied::class, 1);
        \Illuminate\Support\Facades\Event::assertDispatched(\Ulams\CourseBuilder\Events\CourseVersionApplied::class, fn ($e) => $e->release->number === 1 && $e->version->id === $session->applied_version_id && $e->session->id === $session->id);

        app(ReleaseRecorder::class)->applied($session, Version::query()->findOrFail($session->applied_version_id), (int) $session->course_id);

        \Illuminate\Support\Facades\Event::assertDispatchedTimes(\Ulams\CourseBuilder\Events\CourseVersionApplied::class, 1);
    }

    public function testAFailedApplyWritesNoRelease(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        // an admin edits the course after the apply, then the blueprint changes the same element
        $doc = $session->currentVersion->document;
        $lessonId = $doc['modules'][0]['lessons'][0]['id'];
        $entry = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lessonId)->where('entity_type', 'topic')->firstOrFail();
        DB::table('topics')->where('id', $entry->entity_id)->update(['summary' => 'Edited by an admin', 'updated_at' => now()->addMinutes(5)]);
        $doc['modules'][0]['lessons'][0]['blocks'][0]['markdown'] .= "\n\nAn extra sentence.";
        $versions = app(VersionService::class);
        $version = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'edit', null, [], $author->getKey());
        $versions->setCurrent($session, $version);

        $run = Run::query()->findOrFail($this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/apply")->assertStatus(202)->json('data.runId'));

        $this->assertSame('failed', $run->status);
        $this->assertCount(1, $this->releases($session->refresh()));
        $this->assertNotSame($version->id, $session->applied_version_id);
    }

    public function testBackfillGivesAnOlderAppliedSessionItsFirstReleaseOnce(): void
    {
        $session = $this->toApplied($this->author());
        Release::query()->where('session_id', $session->id)->delete();

        $this->artisan('course-builder:backfill-releases', ['--dry-run' => true])->expectsOutput('Would record 1 release(s).')->assertSuccessful();
        $this->assertSame([], $this->releases($session));
        $this->artisan('course-builder:backfill-releases')->expectsOutput('Recorded 1 release(s).')->assertSuccessful();
        $this->artisan('course-builder:backfill-releases')->expectsOutput('Recorded 0 release(s).')->assertSuccessful();

        $releases = $this->releases($session);
        $this->assertCount(1, $releases);
        $this->assertSame([1, 'generate', $session->applied_version_id], [$releases[0]->number, $releases[0]->trigger, $releases[0]->version_id]);
        $this->assertEquals(Version::query()->find($session->applied_version_id)->decided_at, $releases[0]->released_at);
    }
}
