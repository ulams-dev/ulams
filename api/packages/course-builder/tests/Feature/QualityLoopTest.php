<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Critique;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Tests\TestCase;

/** ADR 0051: generate → critique → fix with a retry budget; the failures that remain are flagged. */
class QualityLoopTest extends TestCase
{
    private function failingPedagogy(string $problem = 'The second block repeats the first.'): \Closure
    {
        return function (DriverRequest $request) use ($problem) {
            preg_match('/<task_input>(.*)<\/task_input>/s', implode("\n", array_map(fn ($b) => $b->text, $request->blocks)), $m);
            $input = json_decode($m[1], true);
            $ok = ['ok' => true, 'reason' => 'Fine.'];
            $data = [
                'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'covered' => true, 'reason' => 'Taught.'], $input['objectives']),
                'difficultyIncreases' => $ok, 'noAnswerLeak' => $ok, 'pillars' => ['alignment' => $ok, 'agency' => $ok, 'scaffolding' => $ok, 'feedback' => $ok],
                'issues' => [['elementId' => $input['blocks'][0]['id'], 'problem' => $problem]],
            ];

            return new DriverResponse((string) json_encode($data), 'fake-model', 'end_turn', new Usage(100, 50), 'q');
        };
    }

    private function generated(): Session
    {
        $author = $this->author();
        $session = $this->toOutline($author, 'coffee-brewing.md', ['quiz']);
        $outline = $session->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);

        return $session->refresh();
    }

    public function testEveryLessonIsReviewedByAllCriticsAndTheResultsAreStored(): void
    {
        $session = $this->generated();
        $lessons = count(iterator_to_array(Blueprint::lessons($session->currentVersion->document), false));

        $this->assertSame(Session::APPLY_REVIEW, $session->status);
        $this->assertSame($lessons, Step::query()->whereIn('run_id', \Ulams\CourseBuilder\Models\Run::query()->where('session_id', $session->id)->select('id'))->where('key', 'like', 'critique:%')->where('status', 'done')->count());
        $rows = Critique::query()->where('session_id', $session->id)->get();
        $this->assertSame($lessons * 5, $rows->count());
        $this->assertEqualsCanonicalizing(['grounding', 'mechanics', 'accessibility', 'pedagogy', 'ux'], $rows->pluck('critic')->unique()->all());
        $this->assertSame(['pass'], $rows->pluck('verdict')->unique()->all());
        $this->assertSame([$session->current_version_id], $rows->pluck('version_id')->unique()->all());
        $this->assertSame($lessons, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'critic_pedagogy')->count());
        $this->assertSame(0, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'refine')->count());
    }

    public function testAFailureIsFixedAndChecksAgain(): void
    {
        $session = $this->toOutline($this->author(), 'coffee-brewing.md', ['quiz']);
        $this->fake()->queue('critic_pedagogy', $this->failingPedagogy());
        $outline = $session->current_version_id;
        $this->action($session->author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);
        $session->refresh();

        $this->assertSame(1, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'refine')->count());
        $first = iterator_to_array(Blueprint::lessons($session->currentVersion->document), false)[0]['lesson'];
        $this->assertStringContainsString('(revised)', $first['blocks'][0]['markdown']);
        $this->assertSame([], $first['flags'], 'fixed, so nothing is flagged');
        $rows = Critique::query()->where('session_id', $session->id)->where('element_id', $first['id'])->where('critic', 'pedagogy')->orderBy('iteration')->get();
        $this->assertSame(['fail', 'pass'], $rows->pluck('verdict')->all());
        $this->assertSame([0, 1], $rows->pluck('iteration')->all());
        $this->assertSame('The second block repeats the first.', $rows[0]->issues[0]['problem']);
    }

    public function testTheLoopStopsAtTheIterationCapAndFlagsTheElement(): void
    {
        $session = $this->toOutline($this->author(), 'coffee-brewing.md', ['quiz']);
        foreach (range(1, 3) as $i) {
            $this->fake()->queue('critic_pedagogy', $this->failingPedagogy('Still repetitive.'));
        }
        $outline = $session->current_version_id;
        $this->action($session->author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);
        $session->refresh();

        $this->assertSame(2, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'refine')->count(), 'two fixes, then stop');
        $first = iterator_to_array(Blueprint::lessons($session->currentVersion->document), false)[0]['lesson'];
        $this->assertContains('Pedagogy review: Still repetitive.', $first['flags']);

        // the flag reaches the publish summary as a warning, never a blocker
        $this->action($session->author, $session, 'approve_apply', "apply-{$session->current_version_id}", ['versionId' => $session->current_version_id])->assertStatus(202);
        $check = $this->actingAs($session->author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/publish-check")->assertOk()->json('data');
        $this->assertContains('critique_failed', array_column($check['warnings'], 'code'));
        $this->assertNotContains('critique_failed', array_column($check['blocking'], 'code'));
        $this->assertSame(1, $check['facts']['quality']['critics']['pedagogy']['fail']);
        $this->assertSame(2, $check['facts']['quality']['iterations']);

        $api = $this->actingAs($session->author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/critiques")->assertOk()->json('data');
        $this->assertSame($session->current_version_id, $api['versionId']);
        $failed = array_values(array_filter($api['items'], fn ($i) => $i['verdict'] === 'fail'));
        $this->assertCount(1, $failed);
        $this->assertSame('pedagogy', $failed[0]['critic']);
    }

    public function testWhenTheCriticBudgetIsSpentTheModelCriticsAreSkippedAndShown(): void
    {
        config(['course_builder.quality.critic_usd' => 0.0000001]);
        $session = $this->generated();

        $this->assertSame(0, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->whereIn('task', ['critic_pedagogy', 'critic_ux', 'refine'])->count());
        $rows = Critique::query()->where('session_id', $session->id)->whereIn('critic', ['pedagogy', 'ux'])->get();
        $this->assertNotEmpty($rows);
        $this->assertSame(['skipped'], $rows->pluck('verdict')->unique()->all());
        $this->assertSame(['pass'], Critique::query()->where('session_id', $session->id)->whereIn('critic', ['mechanics', 'accessibility'])->pluck('verdict')->unique()->all(), 'the deterministic critics still run');
        $this->action($session->author, $session, 'approve_apply', "apply-{$session->current_version_id}", ['versionId' => $session->current_version_id])->assertStatus(202);
        $check = $this->actingAs($session->author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/publish-check")->json('data');
        $this->assertContains('critique_skipped', array_column($check['warnings'], 'code'));
    }

    public function testTheModelCriticsCanBeSwitchedOff(): void
    {
        config(['course_builder.quality.llm_critics' => false]);
        $session = $this->generated();

        $this->assertSame(0, AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->whereIn('task', ['critic_pedagogy', 'critic_ux'])->count());
        $this->assertEqualsCanonicalizing(['grounding', 'mechanics', 'accessibility'], Critique::query()->where('session_id', $session->id)->pluck('critic')->unique()->all());
    }
}
