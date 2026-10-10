<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Topic;

/** L2-15: translate or re-level the whole course through the queued pipeline, as one reviewable diff. */
class GlobalEditTest extends TestCase
{
    private function go(Session $session, array $body)
    {
        return $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/global-edit", $body);
    }

    private function applied(): Session
    {
        return $this->toApplied($this->author(), 'coffee-brewing.md', ['quiz', 'final']);
    }

    public function testWithoutConfirmationItOnlyEstimates(): void
    {
        $session = $this->applied();
        $calls = AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->count();
        $lessons = count(iterator_to_array(Blueprint::lessons($session->currentVersion->document), false));

        $data = $this->go($session, ['kind' => 'translate', 'value' => 'pl'])->assertOk()->json('data');

        $this->assertSame('needs_confirmation', $data['state']);
        $this->assertSame($lessons + 2, $data['steps'], 'a step per lesson, the course details and the final test');
        $this->assertGreaterThan(0, $data['estimateMicroUsd']);
        $this->assertStringContainsString('Polish', $data['message']);
        $this->assertSame($calls, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->count(), 'no model call before the author confirms');
        $this->assertSame(0, Run::query()->where('session_id', $session->id)->where('kind', 'global')->count());
    }

    public function testATranslationBecomesOneProposalThatKeepsIdsCitationsAndAnswers(): void
    {
        $session = $this->applied();
        $before = $session->currentVersion;
        $doc = $before->document;

        $this->go($session, ['kind' => 'translate', 'value' => 'pl', 'confirmed' => true])->assertStatus(202);

        $session->refresh();
        $this->assertSame($before->id, $session->current_version_id, 'nothing changed before the approval');
        $proposal = Version::query()->where('session_id', $session->id)->where('status', Version::PROPOSED)->latest('number')->firstOrFail();
        $this->assertSame('patch', $proposal->kind);
        $this->assertNull($proposal->element_id);
        $this->assertSame($before->id, $proposal->parent_id);
        $new = $proposal->document;
        $this->assertSame('pl', $new['course']['language']);
        $this->assertSame(Blueprint::stats($doc)['lessons'], Blueprint::stats($new)['lessons']);
        $this->assertStringStartsWith('[pl] ', $new['course']['title']);

        foreach (Blueprint::lessons($doc) as $i => $item) {
            $old = $item['lesson'];
            $now = iterator_to_array(Blueprint::lessons($new), false)[$i]['lesson'];
            $this->assertSame($old['id'], $now['id']);
            $this->assertStringStartsWith('[pl] ', $now['title']);
            $this->assertSame(array_column($old['blocks'], 'id'), array_column($now['blocks'], 'id'));
            $this->assertSame(array_column($old['blocks'], 'citations'), array_column($now['blocks'], 'citations'), 'translation keeps the citations');
            $this->assertSame(array_column($old['objectives'], 'id'), array_column($now['objectives'], 'id'));
            foreach ($old['quiz']['questions'] ?? [] as $q => $question) {
                $translated = $now['quiz']['questions'][$q];
                $this->assertSame($question['id'], $translated['id']);
                $this->assertSame($question['type'], $translated['type']);
                $this->assertSame(array_column($question['options'], 'correct'), array_column($translated['options'], 'correct'));
                $this->assertSame($question['citations'], $translated['citations']);
                $this->assertNotSame($question['stem'], $translated['stem']);
            }
        }
        $this->assertStringStartsWith('[pl] ', $new['finalTest']['questions'][0]['stem']);

        // one call per part, all logged
        $this->assertSame(Blueprint::stats($doc)['lessons'] + 2, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'global')->count());
        $surface = $session->stateValue("surfaces.patch-{$proposal->id}");
        $this->assertSame(['versionId' => $proposal->id, 'elementId' => ''], array_intersect_key($surface, ['versionId' => 1, 'elementId' => 1]));
    }

    public function testApprovingAppliesTheTranslationToTheCourse(): void
    {
        $session = $this->applied();
        $lesson = $session->currentVersion->document['modules'][0]['lessons'][0];
        $this->go($session, ['kind' => 'translate', 'value' => 'pl', 'confirmed' => true])->assertStatus(202);
        $proposal = Version::query()->where('session_id', $session->id)->where('status', Version::PROPOSED)->latest('number')->firstOrFail();

        $this->action($session->author, $session, 'approve_patch', "patch-{$proposal->id}", ['versionId' => $proposal->id])->assertStatus(202);

        $session->refresh();
        $this->assertSame($proposal->id, $session->current_version_id);
        $this->assertSame($proposal->id, $session->applied_version_id);
        $topicId = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'topic')->where('element_id', $lesson['id'])->value('entity_id');
        $this->assertStringStartsWith('[pl] ', Topic::query()->find($topicId)->title);
        $this->assertSame('pl', \Ulams\Courses\Models\Course::query()->find($session->course_id)->language);
    }

    public function testAFailedPartIsRetriedAloneAndTheRestIsKept(): void
    {
        $session = $this->applied();
        $this->fake()->queue('global', new DriverResponse('', 'fake-model', 'refusal', new Usage(10, 0), 'r', 'cyber'));

        $this->go($session, ['kind' => 'change_level', 'value' => 'beginner', 'confirmed' => true])->assertStatus(202);

        $run = Run::query()->where('session_id', $session->id)->where('kind', 'global')->firstOrFail();
        $this->assertSame('needs_attention', $run->status);
        $failed = Step::query()->where('run_id', $run->id)->where('status', 'failed')->get();
        $this->assertCount(1, $failed);
        $this->assertGreaterThan(1, Step::query()->where('run_id', $run->id)->where('status', 'done')->count(), 'the other parts finished');
        $this->assertSame(0, Version::query()->where('session_id', $session->id)->where('status', Version::PROPOSED)->count());

        $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/runs/{$run->id}/steps/{$failed[0]->id}/retry")->assertStatus(202);

        $this->assertSame('finished', $run->refresh()->status);
        $proposal = Version::query()->where('session_id', $session->id)->where('status', Version::PROPOSED)->firstOrFail();
        $this->assertStringStartsWith('[beginner] ', $proposal->document['course']['title']);
    }

    public function testRejectingKeepsTheCourse(): void
    {
        $session = $this->applied();
        $current = $session->current_version_id;
        $this->go($session, ['kind' => 'change_tone', 'value' => 'playful', 'confirmed' => true])->assertStatus(202);
        $proposal = Version::query()->where('session_id', $session->id)->where('status', Version::PROPOSED)->firstOrFail();

        $this->action($session->author, $session, 'reject_patch', "patch-{$proposal->id}", ['versionId' => $proposal->id])->assertStatus(202);

        $this->assertSame(Version::REJECTED, $proposal->refresh()->status);
        $this->assertSame($current, $session->refresh()->current_version_id);
    }

    public function testInstructionsAreValidatedAndCapped(): void
    {
        $session = $this->applied();
        $this->go($session, ['kind' => 'translate', 'value' => 'polish'])->assertStatus(422);
        $this->go($session, ['kind' => 'change_level', 'value' => 'expert'])->assertStatus(422);
        $this->go($session, ['kind' => 'custom', 'text' => '<script>x</script>'])->assertStatus(422);
        $this->go($session, ['kind' => 'explode'])->assertStatus(422);
        $this->go($session, ['kind' => 'custom', 'text' => 'Use metric units everywhere'])->assertOk()->assertJsonPath('data.state', 'needs_confirmation');

        config(['course_builder.global_edit.max_usd' => 0.000001]);
        $this->go($session, ['kind' => 'translate', 'value' => 'pl', 'confirmed' => true])->assertStatus(409)->assertJsonPath('data.state', 'blocked');
        $this->assertSame(0, Run::query()->where('session_id', $session->id)->where('kind', 'global')->count());
    }

    public function testItNeedsGeneratedApprovedContent(): void
    {
        $author = $this->author();
        $outline = $this->toOutline($author);
        $this->go($outline, ['kind' => 'translate', 'value' => 'pl'])->assertStatus(422);
        $this->actingAs($this->tutor(), 'api')->postJson("/api/admin/course-builder/sessions/{$outline->id}/global-edit", ['kind' => 'translate', 'value' => 'pl'])->assertStatus(403);
    }
}
