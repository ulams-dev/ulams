<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;

/** L2-13: "give me options" makes 2–3 proposals, compared side by side; choosing one rejects the others. */
class VariantComparisonTest extends TestCase
{
    private function question(Session $session): array
    {
        foreach ($session->currentVersion->document['modules'] as $module) {
            foreach ($module['lessons'] as $lesson) {
                if (!empty($lesson['quiz']['questions'])) {
                    return $lesson['quiz']['questions'][0];
                }
            }
        }
        $this->fail('no question');
    }

    private function ask(Session $session, string $element, array $body)
    {
        return $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/elements/{$element}/variants", $body);
    }

    public function testTwoOptionsShareAGroupAndAreShownSideBySide(): void
    {
        $session = $this->toApplied($this->author());
        $question = $this->question($session);
        $before = AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'patch')->count();

        $this->ask($session, $question['id'], ['count' => 2, 'instruction' => 'Make the wrong answers harder'])->assertStatus(202);

        $versions = Version::query()->where('session_id', $session->id)->whereNotNull('variant_group')->get();
        $this->assertCount(2, $versions);
        $this->assertSame(1, $versions->pluck('variant_group')->unique()->count());
        $this->assertSame([Version::PROPOSED], $versions->pluck('status')->unique()->all());
        $this->assertSame($question['id'], $versions[0]->element_id);
        $this->assertSame($before + 2, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'patch')->count(), 'one model call per option');

        $surface = $session->refresh()->stateValue('surfaces.variants-' . $versions[0]->variant_group);
        $this->assertSame('variants', $surface['kind']);
        $this->assertTrue($surface['open']);
        $this->assertTrue(Run::query()->where('session_id', $session->id)->where('kind', 'variants')->where('status', 'finished')->exists());
    }

    public function testChoosingOneApprovesItRejectsTheOthersAndReappliesTheCourse(): void
    {
        $session = $this->toApplied($this->author());
        $question = $this->question($session);
        $this->ask($session, $question['id'], ['count' => 3, 'instruction' => 'Shorter explanation'])->assertStatus(202);
        $versions = Version::query()->where('session_id', $session->id)->whereNotNull('variant_group')->orderBy('number')->get();
        $this->assertCount(3, $versions);
        $group = $versions[0]->variant_group;

        $this->action($session->author, $session, 'choose_variant', "variants-{$group}", ['versionId' => $versions[1]->id])->assertStatus(202);

        $this->assertSame(Version::APPROVED, $versions[1]->refresh()->status);
        $this->assertSame(Version::REJECTED, $versions[0]->refresh()->status);
        $this->assertSame(Version::REJECTED, $versions[2]->refresh()->status);
        $session->refresh();
        $this->assertSame($versions[1]->id, $session->current_version_id);
        $this->assertSame($versions[1]->id, $session->applied_version_id, 'the chosen option is applied to the course');
        $this->assertFalse($session->stateValue("surfaces.variants-{$group}.open"));

        // an option that was not chosen can no longer be picked
        $this->action($session->author, $session, 'choose_variant', "variants-{$group}", ['versionId' => $versions[0]->id])->assertJsonPath('data.accepted', false);
        $this->assertSame(Version::REJECTED, $versions[0]->refresh()->status);
    }

    public function testRejectingTheGroupKeepsTheCourseAsItIs(): void
    {
        $session = $this->toApplied($this->author());
        $question = $this->question($session);
        $current = $session->current_version_id;
        $this->ask($session, $question['id'], ['count' => 2, 'instruction' => 'Another angle'])->assertStatus(202);
        $group = Version::query()->where('session_id', $session->id)->whereNotNull('variant_group')->value('variant_group');

        $this->action($session->author, $session, 'reject_variants', "variants-{$group}", ['group' => $group])->assertStatus(202);

        $this->assertSame([Version::REJECTED], Version::query()->where('variant_group', $group)->pluck('status')->unique()->all());
        $this->assertSame($current, $session->refresh()->current_version_id);
    }

    public function testAskingForOptionsInTheChatStartsTheComparison(): void
    {
        $session = $this->toApplied($this->author());
        $question = $this->question($session);

        $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'messages' => [['role' => 'user', 'content' => 'Give me options for the wrong answers']],
            'forwardedProps' => ['selection' => ['elementId' => $question['id']]],
        ])->assertStatus(202);

        $this->assertSame(2, Version::query()->where('session_id', $session->id)->whereNotNull('variant_group')->count());
    }

    public function testValidation(): void
    {
        $session = $this->toApplied($this->author());
        $question = $this->question($session);
        $this->ask($session, $question['id'], ['count' => 5, 'instruction' => 'x y'])->assertStatus(422);
        $this->ask($session, $question['id'], ['count' => 2])->assertStatus(422);
        $this->ask($session, '01zzzzzzzzzzzzzzzzzzzzzzzz', ['count' => 2, 'instruction' => 'More options'])->assertStatus(409);
        $this->actingAs($this->tutor(), 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/elements/{$question['id']}/variants", ['count' => 2, 'instruction' => 'More options'])->assertStatus(403);
    }
}
