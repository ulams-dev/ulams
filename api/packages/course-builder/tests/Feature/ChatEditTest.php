<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Lesson;
use Ulams\TopicTypeGift\Models\GiftQuestion;

/** Element chat → proposed version as a diff → approve → re-apply; undo, redo, restore. */
class ChatEditTest extends TestCase
{
    private function firstQuestion(Session $session): array
    {
        foreach (Blueprint::lessons($session->currentVersion->document) as $item) {
            if (!empty($item['lesson']['quiz']['questions'])) {
                return $item['lesson']['quiz']['questions'][0];
            }
        }
        $this->fail('no quiz question');
    }

    private function ask(Session $session, $author, string $elementId, string $text)
    {
        return $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'threadId' => $session->id,
            'runId' => 'r',
            'messages' => [['id' => 'm1', 'role' => 'user', 'content' => $text]],
            'forwardedProps' => ['selection' => ['elementId' => $elementId]],
        ]);
    }

    private function giftOf(Session $session, string $questionId): GiftQuestion
    {
        $entry = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $questionId)->where('entity_type', 'gift_question')->firstOrFail();

        return GiftQuestion::query()->findOrFail($entry->entity_id);
    }

    public function testQuestionPatchIsProposedAsADiffAndAppliedOnApproval(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $question = $this->firstQuestion($session);
        $before = $this->giftOf($session, $question['id'])->value;

        $this->ask($session, $author, $question['id'], 'Make the distractors less obvious')->assertStatus(202);
        $session->refresh();
        $patch = Version::query()->where('session_id', $session->id)->where('kind', 'patch')->latest('number')->firstOrFail();
        $this->assertSame(Version::PROPOSED, $patch->status);
        $this->assertSame($question['id'], $patch->element_id);
        $this->assertSame('Make the distractors less obvious', $patch->reason);
        // nothing changes before approval
        $this->assertNotSame($patch->id, $session->current_version_id);
        $this->assertSame($before, $this->giftOf($session, $question['id'])->value);

        $surface = Event::query()->where('session_id', $session->id)->where('type', 'ACTIVITY_SNAPSHOT')->get()->last(fn ($e) => $e->payload['messageId'] === "patch-{$patch->id}");
        $components = collect($surface->payload['content']['messages'][1]['updateComponents']['components'])->keyBy('id');
        $this->assertSame('DiffView', $components['diff']['component']);
        $this->assertNotEmpty($components['diff']['changes']);
        $this->assertSame('QuizQuestionCard', $components['preview']['component']);
        // ids preserved
        $patched = Blueprint::find($patch->document, $question['id'])['node'];
        $this->assertSame(array_column($question['options'], 'id'), array_column($patched['options'], 'id'));

        $this->action($author, $session, 'approve_patch', "patch-{$patch->id}", ['versionId' => $patch->id])->assertStatus(202);
        $session->refresh();
        $this->assertSame($patch->id, $session->current_version_id);
        $this->assertSame($patch->id, $session->applied_version_id);
        $after = $this->giftOf($session, $question['id'])->value;
        $this->assertNotSame($before, $after);
        $this->assertStringContainsString('in most cases', $after);

        // undo restores the question in the LMS, redo brings the change back
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/undo")->assertOk();
        $this->assertSame($before, $this->giftOf($session->refresh(), $question['id'])->value);
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/redo")->assertOk();
        $this->assertSame($after, $this->giftOf($session->refresh(), $question['id'])->value);
    }

    public function testRejectKeepsTheCurrentVersion(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $current = $session->current_version_id;
        $question = $this->firstQuestion($session);
        $this->ask($session, $author, $question['id'], 'Shorter please')->assertStatus(202);
        $patch = Version::query()->where('session_id', $session->id)->where('kind', 'patch')->latest('number')->firstOrFail();

        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/versions/{$patch->id}/reject")->assertOk();
        $this->assertSame(Version::REJECTED, $patch->refresh()->status);
        $this->assertSame($current, $session->refresh()->current_version_id);
        // a decided surface cannot be approved any more
        $this->action($author, $session, 'approve_patch', "patch-{$patch->id}", ['versionId' => $patch->id])->assertStatus(202)->assertJsonPath('data.accepted', false);
    }

    public function testRestoreCreatesANewVersionFromAnOldOne(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $original = $session->currentVersion;
        $lesson = iterator_to_array(Blueprint::lessons($original->document), false)[0]['lesson'];
        $this->ask($session, $author, $lesson['id'], 'Rewrite the summary')->assertStatus(202);
        $patch = Version::query()->where('session_id', $session->id)->where('kind', 'patch')->latest('number')->firstOrFail();
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/versions/{$patch->id}/approve")->assertOk();

        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/versions/{$original->id}/restore")->assertOk();
        $session->refresh();
        $restored = $session->currentVersion;
        $this->assertSame('restore', $restored->kind);
        $this->assertSame($original->document, $restored->document);
        $this->assertSame($restored->id, $session->applied_version_id);
        $history = $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/versions")->assertOk()->json('data.versions');
        $this->assertSame(['outline', 'content', 'patch', 'restore'], array_column($history, 'kind'));
    }

    public function testRemovingAModuleDeletesItsLmsLessonOnReapply(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $doc = $session->currentVersion->document;
        $this->assertGreaterThan(1, count($doc['modules']));
        $removed = array_pop($doc['modules']);
        $lessonId = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $removed['id'])->value('entity_id');
        $this->assertNotNull(Lesson::query()->find($lessonId));

        $versions = app(\Ulams\CourseBuilder\Services\VersionService::class);
        $edit = $versions->create($session, $doc, 'author', 'author', Version::APPROVED, $session->currentVersion, 'Remove the last module', null, [], $author->getKey());
        $versions->setCurrent($session, $edit);
        app(\Ulams\CourseBuilder\Services\RunService::class)->reapply($session->refresh(), $author->getKey());

        $this->assertNull(Lesson::query()->find($lessonId));
        $this->assertSame(0, EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $removed['id'])->count());
    }

    public function testChatWithoutSelectionAsksForOne(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", ['messages' => [['role' => 'user', 'content' => 'Make it better']]])
            ->assertStatus(202)->assertJsonPath('data.runId', null);
        $this->assertStringContainsString('Select a lesson', Event::query()->where('session_id', $session->id)->where('type', 'TEXT_MESSAGE_CONTENT')->latest('id')->first()->payload['delta']);
    }

    public function testPublishGoesThroughTheCoursesService(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/publish")->assertOk();
        $this->assertSame('published', \Ulams\Courses\Models\Course::query()->find($session->course_id)->status);
    }
}
