<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Ulams\Ai\Models\AiCall;
use Ulams\AuthoringMetrics\Contracts\SubjectCourseResolver;
use Ulams\AuthoringMetrics\Models\CourseMetric;
use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\Services\SubjectResolvers;
use Ulams\AuthoringMetrics\Tests\TestCase;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;

/** The per-course snapshot (AM-3): time, cost and the split of AI elements, from what was recorded. LLM is the fake driver. */
class CourseMetricsTest extends TestCase
{
    private function applied(): Session
    {
        return $this->toApplied($this->author(), 'coffee-brewing.md', ['quiz', 'final']);
    }

    private function aiCall(string $type, string $id, int $cost, string $status = 'ok', int $latency = 1000): void
    {
        AiCall::query()->create([
            'task' => 'lesson', 'driver' => 'fake', 'model_requested' => 'm', 'status' => $status, 'cost_micro_usd' => $cost,
            'latency_ms' => $latency, 'subject_type' => $type, 'subject_id' => $id,
        ]);
    }

    private function metric(Session $session): CourseMetric
    {
        return app(CourseMetrics::class)->recompute((int) $session->refresh()->course_id);
    }

    public function testAnAppliedBuildHasTimeCostAndCountsThatAddUp(): void
    {
        $session = $this->applied();
        $metric = $this->metric($session);

        $doc = $session->currentVersion->document;
        $stats = Blueprint::stats($doc);
        $this->assertSame($session->id, $metric->session_id);
        $this->assertSame([$stats['lessons'], $stats['questions'], $stats['blocks']], [(int) $metric->lessons, (int) $metric->questions, (int) $metric->blocks]);
        $this->assertSame((int) $metric->blocks, (int) $metric->cited_blocks, 'every generated block cites a passage');
        $this->assertSame(1, (int) $metric->method_version);

        $kinds = StageTiming::query()->where('session_id', $session->id)->selectRaw('run_kind, sum(seconds) s')->groupBy('run_kind')->pluck('s', 'run_kind');
        $this->assertEqualsWithDelta((float) ($kinds['outline'] ?? 0) + (float) ($kinds['generate'] ?? 0) + (float) ($kinds['apply'] ?? 0), (float) $metric->generation_seconds, 0.01);
        $this->assertSame('0.000', (string) $metric->ai_edit_seconds);

        $calls = AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->get();
        $this->assertGreaterThan(5, $calls->count());
        $this->assertSame($calls->count(), (int) $metric->llm_calls);
        $this->assertSame((int) $calls->sum('cost_micro_usd'), (int) $metric->cost_build_micro_usd);
        $this->assertSame(0, (int) $metric->cost_maintenance_micro_usd);
        $this->assertEqualsWithDelta($calls->sum('latency_ms') / 1000, (float) $metric->model_seconds, 0.001);
        $this->assertNull($metric->author_minutes, 'review time is not recorded: never a made-up zero');
    }

    public function testEveryElementTheAiWroteIsAcceptedUnchangedUntilSomeoneEditsIt(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $metric = $this->metric($session);

        $this->assertGreaterThan(0, (int) $metric->ai_elements);
        $this->assertSame((int) $metric->ai_elements, (int) $metric->ai_accepted_unchanged);
        $this->assertSame([0, 0, 0, 0], [(int) $metric->ai_edited_by_author, (int) $metric->ai_revised_on_request, (int) $metric->ai_unknown, (int) $metric->author_elements]);

        $lesson = $session->refresh()->currentVersion->document['modules'][0]['lessons'][0];
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/outline", ['action' => 'rename', 'id' => $lesson['id'], 'title' => 'A better title'])->assertOk();

        $after = $this->metric($session);
        $this->assertSame(1, (int) $after->ai_edited_by_author);
        $this->assertSame((int) $metric->ai_elements - 1, (int) $after->ai_accepted_unchanged);
        $this->assertSame((int) $metric->ai_elements, (int) $after->ai_elements, 'the edited element is still one the AI wrote');
    }

    public function testAChatEditCountsAsRevisedOnRequest(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $before = $this->metric($session);
        $question = null;
        foreach (Blueprint::lessons($session->currentVersion->document) as $item) {
            $question ??= $item['lesson']['quiz']['questions'][0] ?? null;
        }
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'threadId' => $session->id, 'runId' => 'r',
            'messages' => [['id' => 'm1', 'role' => 'user', 'content' => 'Make the distractors less obvious']],
            'forwardedProps' => ['selection' => ['elementId' => $question['id']]],
        ])->assertStatus(202);
        $patch = Version::query()->where('session_id', $session->id)->where('kind', 'patch')->latest('number')->firstOrFail();
        $this->action($author, $session, 'approve_patch', "patch-{$patch->id}", ['versionId' => $patch->id])->assertStatus(202);

        $after = $this->metric($session);

        $this->assertSame(1, (int) $after->ai_revised_on_request);
        $this->assertSame((int) $before->ai_accepted_unchanged - 1, (int) $after->ai_accepted_unchanged);
        $this->assertGreaterThan((int) $before->llm_calls, (int) $after->llm_calls, 'the chat edit calls were counted');
        $this->assertGreaterThanOrEqual(0.0, (float) $after->ai_edit_seconds);
    }

    public function testElementsFromBeforeProvenanceAreUnknownNotGuessed(): void
    {
        $session = $this->applied();
        $version = Version::query()->findOrFail($session->refresh()->applied_version_id);
        $strip = function (array $n) use (&$strip) {
            unset($n['ai_hash'], $n['edited_by_author']);

            return array_map(fn ($v) => is_array($v) ? $strip($v) : $v, $n);
        };
        $version->forceFill(['document' => $strip($version->document)])->save();

        $metric = $this->metric($session);

        $this->assertSame(0, (int) $metric->ai_accepted_unchanged);
        $this->assertGreaterThan(0, (int) $metric->ai_unknown);
        $this->assertSame((int) $metric->ai_elements, (int) $metric->ai_unknown);
    }

    public function testOnlyAuthoringSubjectsCountAndFailedCallsAreCounted(): void
    {
        $session = $this->applied();
        $base = $this->metric($session);
        $variant = Variant::query()->create(['session_id' => $session->id, 'key' => 'expert', 'label' => 'Expert', 'audience_brief' => [], 'status' => 'draft', 'position' => 1]);

        $this->aiCall(Session::SUBJECT_TYPE, $session->id, 1000, 'invalid');
        $this->aiCall(Variant::SUBJECT_TYPE, (string) $variant->id, 2000);
        $this->aiCall('tutor_conversation', $session->id, 999999);
        $this->aiCall('learner_remediation', $session->id, 999999);
        $this->aiCall(Session::SUBJECT_TYPE, '01ARZ3NDEKTSV4RRFFQ69G5FAV', 999999); // another session
        $metric = $this->metric($session);

        $this->assertSame((int) $base->cost_build_micro_usd + 3000, (int) $metric->cost_build_micro_usd, 'builder and variant calls count, tutor and remediation do not');
        $this->assertSame((int) $base->llm_calls + 2, (int) $metric->llm_calls);
        $this->assertSame((int) $base->llm_calls_failed + 1, (int) $metric->llm_calls_failed, 'a failed call was paid for and is counted, and counted as failed');
    }

    public function testAPackageResolvesItsOwnSubjectsIntoTheMaintenanceBucket(): void
    {
        $session = $this->applied();
        $base = $this->metric($session);
        app(SubjectResolvers::class)->register(new class ($session->id) implements SubjectCourseResolver {
            public function __construct(private string $session)
            {
            }

            public function subjectType(): string
            {
                return 'living_course_proposal';
            }

            public function bucket(): string
            {
                return SubjectResolvers::MAINTENANCE;
            }

            public function subjectIds(array $sessionIds): array
            {
                return in_array($this->session, $sessionIds, true) ? ['p1', 'p2'] : [];
            }
        });
        $this->aiCall('living_course_proposal', 'p1', 400);
        $this->aiCall('living_course_proposal', 'p2', 600, 'error');
        $this->aiCall('living_course_proposal', 'p3', 5000);

        $metric = $this->metric($session);

        $this->assertSame((int) $base->cost_build_micro_usd, (int) $metric->cost_build_micro_usd);
        $this->assertSame(1000, (int) $metric->cost_maintenance_micro_usd);
        $this->assertSame((int) $base->llm_calls + 2, (int) $metric->llm_calls);
    }

    public function testTheSnapshotFollowsTheCourse(): void
    {
        $session = $this->applied();
        $courseId = (int) $session->refresh()->course_id;
        $this->assertNotNull(CourseMetric::query()->find($courseId), 'the apply run recomputed it');

        CourseMetric::query()->whereKey($courseId)->update(['lessons' => 99]);
        Event::dispatch(new \Ulams\Courses\Events\CoursedPublished($this->author(), \Ulams\Courses\Models\Course::query()->findOrFail($courseId)));
        $this->assertNotSame(99, (int) CourseMetric::query()->find($courseId)->lessons, 'publishing recomputes');

        CourseMetric::query()->whereKey($courseId)->update(['lessons' => 99]);
        Event::dispatch('Ulams\\LivingCourse\\Events\\ProposalApplied', [(object) ['proposal' => (object) ['session_id' => $session->id]]]);
        $this->assertNotSame(99, (int) CourseMetric::query()->find($courseId)->lessons, 'an applied proposal recomputes');

        CourseMetric::query()->whereKey($courseId)->update(['lessons' => 99]);
        $this->artisan('authoring-metrics:recompute', ['--course' => $courseId])->expectsOutputToContain('Recomputed 1 course snapshots.')->assertSuccessful();
        $this->assertNotSame(99, (int) CourseMetric::query()->find($courseId)->lessons);

        CourseMetric::query()->whereKey($courseId)->update(['lessons' => 99]);
        Session::query()->whereKey($session->id)->update(['updated_at' => now()->subDays(10)]);
        $this->artisan('authoring-metrics:recompute', ['--active' => true])->assertSuccessful();
        $this->assertSame(99, (int) CourseMetric::query()->find($courseId)->lessons, 'the nightly run skips courses nobody touched');
    }

    public function testACourseWithoutABuilderSessionHasNoSnapshot(): void
    {
        $course = \Ulams\Courses\Models\Course::factory()->create();

        $this->assertNull(app(CourseMetrics::class)->recompute((int) $course->getKey()));
        $this->assertSame(0, CourseMetric::query()->count());
    }

    public function testRecomputingGivesTheSameFiguresForTheSameHistory(): void
    {
        $session = $this->applied();
        $first = $this->metric($session)->toArray();
        $second = $this->metric($session)->toArray();
        unset($first['computed_at'], $second['computed_at']);

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('authoring_course_metrics')->where('course_id', $session->course_id)->count());
    }
}
