<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\Courses\Models\Topic;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\ElementStatus;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Tests\TestCase;
use Ulams\TopicTypeGift\Models\GiftQuestion;

/** Applying accepted items as an update version (plan 8.3). */
class ApplyTest extends TestCase
{
    /** @return array{0:\Ulams\Courses\Tests\Models\User,1:Session,2:Proposal} */
    private function ready(string $upload = 'coffee-brewing.v2.md'): array
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture($upload)])->assertCreated();

        return [$author, $session, Proposal::query()->where('session_id', $session->id)->firstOrFail()];
    }

    private function url(Proposal $p, string $path): string
    {
        return "/api/admin/living-course/proposals/{$p->id}{$path}";
    }

    private function topicOf(Session $session, string $elementId, string $type = 'topic'): ?Topic
    {
        $entry = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $elementId)->where('entity_type', $type)->first();

        return $entry ? Topic::query()->find($entry->entity_id) : null;
    }

    private function lessonId(Session $session, string $number): string
    {
        foreach (\Ulams\CourseBuilder\Blueprint\Blueprint::lessons($session->currentVersion->document) as $item) {
            if ($item['number'] === $number) {
                return $item['lesson']['id'];
            }
        }
        $this->fail("No lesson {$number}");
    }

    public function testApplyingTheAcceptedItemsCreatesAnUpdateVersionAndChangesTheCourse(): void
    {
        [$author, $session, $proposal] = $this->ready();
        $before = $session->current_version_id;
        $lesson22 = $this->lessonId($session, '2.2');
        $lesson21 = $this->lessonId($session, '2.1');
        $oldValue22 = (string) $this->topicOf($session, $lesson22)->topicable->value;
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/accept-all'))->assertOk();
        // the author keeps lesson 2.2 as it was
        $block22 = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->where('element_type', 'block')->where('label', 'like', 'Lesson 2.2%')->get();
        $this->assertCount(2, $block22);
        foreach ($block22 as $item) {
            $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/reject"))->assertOk();
        }
        $removedTopic = $this->topicOf($session, $this->lessonId($session, '4.2'));
        $this->assertNotNull($removedTopic);

        $response = $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(202);

        $run = Run::query()->findOrFail($response->json('data.runId'));
        $this->assertSame('finished', $run->status);
        $proposal->refresh();
        $this->assertSame('applied', $proposal->status);
        $version = Version::query()->findOrFail($proposal->result_version_id);
        $this->assertSame('update', $version->kind);
        $this->assertSame('approved', $version->status);
        $this->assertSame($before, $version->parent_id);
        $this->assertSame([$proposal->source_id => $proposal->to_revision_id], $version->source_revisions);
        $this->assertStringStartsWith('Source update r1 → r2: ', $version->reason);
        $session->refresh();
        $this->assertSame($version->id, $session->current_version_id);
        $this->assertSame($version->id, $session->applied_version_id);

        // the LMS reflects it through the domain services: lesson 2.1 follows the new ratio, lesson 2.2 stays
        $this->assertStringContainsString('1:15', (string) $this->topicOf($session, $lesson21)->topicable->value);
        $this->assertSame($oldValue22, (string) $this->topicOf($session, $lesson22)->topicable->value);
        $quiz = $this->topicOf($session, $session->currentVersion->document['modules'][1]['lessons'][0]['quiz']['id'], 'quiz_topic');
        $gift = str_replace('\\', '', GiftQuestion::query()->where('topic_gift_quiz_id', $quiz->topicable_id)->get()->pluck('value')->implode("\n"));
        $this->assertStringContainsString('1:15', $gift);
        // the removed lesson is gone from the blueprint and the course
        $this->assertNull(collect(\Ulams\CourseBuilder\Blueprint\Blueprint::lessons($session->currentVersion->document))->first(fn ($l) => $l['number'] === '4.2' && str_contains($l['lesson']['title'], 'Water quality')));
        $this->assertNull(Topic::query()->find($removedTopic->id));

        // the revision is promoted: citations now read the new text
        $this->assertStringContainsString('1:15', Fragment::query()->where('source_id', $this->sourceOf($session)->id)->get()->pluck('text')->implode(' '));
        $this->assertSame($proposal->to_revision_id, $this->connectionOf($session)->synced_revision_id);

        // staleness: applied items are in sync, the rest was dismissed
        $this->assertSame(0, ElementStatus::query()->where('session_id', $session->id)->whereIn('status', ['pending', 'source_removed'])->count());
        $this->assertSame(2, ElementStatus::query()->where('session_id', $session->id)->where('status', 'dismissed')->whereIn('element_id', $block22->pluck('element_id'))->count());

        $audit = AuditEntry::query()->where('action', 'proposal.applied')->where('subject_id', $proposal->id)->firstOrFail();
        $this->assertSame($version->number, $audit->version_to);
        $this->assertSame($proposal->to_revision_id, $audit->revision_id);
        $this->assertSame((int) $author->getKey(), $audit->actor_id);
        $this->assertNotEmpty($audit->ai_call_ids);
        $this->assertSame($proposal->counts['applied'], $audit->data['applied']);
        $this->assertTrue(app(\Ulams\LivingCourse\Services\AuditLog::class)->verify()['ok']);
    }

    public function testElementsEditedAfterTheAnalysisAreConflictsAndNothingIsWritten(): void
    {
        [$author, $session, $proposal] = $this->ready();
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/accept-all'))->assertOk();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->where('element_type', 'block')->first();
        // a chat edit changes that block after the analysis
        $doc = $session->currentVersion->document;
        $found = \Ulams\CourseBuilder\Blueprint\Blueprint::find($doc, $item->element_id);
        $found['node']['markdown'] .= "\n\nAn author sentence.";
        $edited = \Ulams\CourseBuilder\Blueprint\Blueprint::setAt($doc, $found['path'], $found['node']);
        $versions = app(VersionService::class);
        $v = $versions->create($session, $edited, 'patch', 'author', Version::APPROVED, $session->currentVersion, 'edit', $item->element_id, [], $author->getKey());
        $versions->setCurrent($session, $v);

        $response = $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(409);

        $this->assertSame('conflicts', $response->json('code'));
        $this->assertSame([$item->id], array_column($response->json('data.conflicts'), 'id'));
        $this->assertSame('conflict', $item->refresh()->status);
        $this->assertSame('ready', $proposal->refresh()->status);
        $this->assertSame($v->id, $session->refresh()->current_version_id);
        $this->assertSame(0, Version::query()->where('session_id', $session->id)->where('kind', 'update')->count());
        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/accept"))->assertStatus(409);

        // rejecting the conflicting item lets the rest through
        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/reject"))->assertOk();
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(202);
        $this->assertSame('applied', $proposal->refresh()->status);
        $final = $session->refresh()->currentVersion->document;
        $this->assertStringContainsString('An author sentence.', \Ulams\CourseBuilder\Blueprint\Blueprint::find($final, $item->element_id)['node']['markdown'], 'the author edit survived');
    }

    public function testMovedSectionsOnlyRewriteCitations(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $oldId = Fragment::query()->where('source_id', $this->sourceOf($session)->id)->get()->first(fn ($f) => $f->label() === '§2.1 The brew ratio')->id;
        $md = str_replace('### The brew ratio', '### The brewing ratio', (string) file_get_contents(__DIR__ . '/../../resources/fixtures/coffee-brewing.v1.md'));
        $path = tempnam(sys_get_temp_dir(), 'lcmv') . '.md';
        file_put_contents($path, $md);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => new UploadedFile($path, 'moved.md', null, null, true)])->assertCreated();
        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $newId = \Ulams\LivingCourse\Models\FragmentChange::query()->where('to_revision_id', $proposal->to_revision_id)->where('kind', 'moved')->value('new_fragment_id');
        $this->assertNotSame($oldId, $newId);
        $topicBefore = (string) $this->topicOf($session, $this->lessonId($session, '2.1'))->topicable->value;

        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(202);

        $doc = $session->refresh()->currentVersion->document;
        $cited = \Ulams\CourseBuilder\Blueprint\Blueprint::citations($doc);
        $this->assertContains($newId, $cited);
        $this->assertNotContains($oldId, $cited);
        $strip = fn (string $v) => trim((string) preg_replace('/\*\*Sources\*\*.*$/s', '', $v));
        $this->assertSame($strip($topicBefore), $strip((string) $this->topicOf($session, $this->lessonId($session, '2.1'))->topicable->value), 'the lesson text did not change, only its source label');
        $this->assertSame('update', $session->currentVersion->kind);
        @unlink($path);
    }

    public function testAdminEditsStopTheApplyUntilTheAuthorConfirmsAnOverwrite(): void
    {
        [$author, $session, $proposal] = $this->ready();
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/accept-all'))->assertOk();
        $topic = $this->topicOf($session, $this->lessonId($session, '2.1'));
        DB::table('topics')->where('id', $topic->id)->update(['summary' => 'Edited by an admin', 'updated_at' => now()->addMinutes(5)]);

        $response = $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(409);
        $this->assertSame('admin_edits', $response->json('code'));
        $this->assertSame('ready', $proposal->refresh()->status);
        $this->assertSame('Edited by an admin', $topic->refresh()->summary);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'), ['overwrite' => true])->assertStatus(202);
        $this->assertSame('applied', $proposal->refresh()->status);
    }

    public function testAnUpdateVersionCanBeUndoneLikeAnyContentVersion(): void
    {
        [$author, $session, $proposal] = $this->ready();
        $before = $session->current_version_id;
        $lesson21 = $this->lessonId($session, '2.1');
        $old = (string) $this->topicOf($session, $lesson21)->topicable->value;
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/accept-all'))->assertOk();
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(202);

        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/undo")->assertOk();

        $this->assertSame($before, $session->refresh()->current_version_id);
        $this->assertSame($old, (string) $this->topicOf($session, $lesson21)->topicable->value);
        $versions = $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/versions")->assertOk()->json('data.versions');
        $this->assertSame('update', collect($versions)->firstWhere('kind', 'update')['kind']);
        $this->assertNotEmpty(collect($versions)->firstWhere('kind', 'update')['sourceRevisions']);
    }

    public function testApplyNeedsAReadyProposalAndAnAuthorizedUser(): void
    {
        [$author, , $proposal] = $this->ready();
        $this->actingAs($this->tutor(), 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(403);
        $this->actingAs($this->student(), 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(403);
        $this->actingAs($this->readOnlyAdmin(), 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(403);
        $this->app['auth']->forgetGuards();
        $this->postJson($this->url($proposal, '/apply'))->assertStatus(401);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(202);
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/apply'))->assertStatus(409);
        $this->actingAs($author, 'api')->postJson('/api/admin/living-course/proposals/01aaaaaaaaaaaaaaaaaaaaaaaa/apply')->assertStatus(404);
    }
}
