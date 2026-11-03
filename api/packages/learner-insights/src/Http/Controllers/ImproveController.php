<?php

namespace Ulams\LearnerInsights\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Enums\LearnerInsightsPermissionsEnum as Permission;
use Ulams\LearnerInsights\Http\Controllers\Swagger\ImproveSwagger;
use Ulams\LearnerInsights\Improve\EvidenceBuilder;
use Ulams\LearnerInsights\Services\Analytics;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Policies\LivingCoursePolicy;
use Ulams\LivingCourse\Services\AnalysisService;
use Ulams\LivingCourse\Services\InsightsProposalService;
use Ulams\LivingCourse\Services\ProposalService;
use Ulams\LivingCourse\Support\Presenter;

/**
 * "Propose an improvement" for an element learners struggle with (ADR 0181). Without `confirmEstimate` it
 * only answers what would be sent and what it would cost; with it, a Living Course proposal of kind
 * `insights` is created and analysed. Who may: someone who may see the course's insights and may act on the
 * builder session of the course (its author, or a holder of `living_course_review`). The model only
 * proposes; the author reviews and applies the diff on the Living Course review screen.
 */
class ImproveController extends Controller implements ImproveSwagger
{
    /** element keys of the Insights page the endpoint answers for */
    public const KEY = '[0-9A-Za-z]{26}|topic:\d+|question:\d+';

    public function __construct(
        private readonly EvidenceBuilder $evidence,
        private readonly ProposalService $proposals,
        private readonly InsightsProposalService $insights,
        private readonly AnalysisService $analysis,
    ) {
    }

    public function store(Request $request, int $course, string $elementKey): JsonResponse
    {
        $model = Course::query()->findOrFail($course);
        $user = $request->user();
        abort_unless($user && ($user->can(Permission::LEARNER_INSIGHTS_VIEW, 'api') || $model->hasAuthor($user)), 403);
        $data = $request->validate(['confirmEstimate' => ['sometimes', 'boolean'], 'period' => ['sometimes', 'integer', Rule::in(Analytics::PERIODS)]]);
        $days = (int) ($data['period'] ?? 30);

        $entry = ctype_alnum($elementKey) && strlen($elementKey) === 26
            ? EntityMapEntry::query()->where('element_id', strtolower($elementKey))->whereNull('retired_at')->orderBy('id')->first()
            : null;
        $session = $entry ? Session::query()->find($entry->session_id) : null;
        if ($session === null || (int) $session->course_id !== $course) {
            return $this->fail('This part of the course was not built with the Course Builder, so it cannot be improved from learner insights. Edit it by hand instead.', 404, 'not_builder_element');
        }
        abort_unless((new LivingCoursePolicy())->act($user, $session), 403, 'Only the author of this course, or someone who reviews Living Course updates, may propose improvements.');
        $elementId = strtolower($elementKey);

        try {
            $this->insights->assertAiEnabled();
            $evidence = $this->evidence->build($course, $elementId, $days);
            if ($evidence === null) {
                return $this->fail(sprintf('Fewer than %d learners were active here in this period, so no improvement can be proposed from their results.', Analytics::minGroup()), 422, 'small_group');
            }
            if (!$request->boolean('confirmEstimate')) {
                $existing = $this->insights->openFor($session, $elementId);
                if ($existing !== null) {
                    return response()->json(['data' => ['state' => 'kept_existing', 'proposal' => Presenter::proposalSummary($existing)]]);
                }
                $estimate = $this->insights->estimate($session, $elementId, $evidence->toArray());

                return response()->json(['data' => [
                    'state' => 'estimate', 'estimateMicroUsd' => $estimate['estimateMicroUsd'], 'estimateUsd' => round($estimate['estimateMicroUsd'] / 1e6, 4),
                    'label' => $estimate['label'], 'elements' => $estimate['elements'], 'evidence' => $evidence->toArray(),
                ]]);
            }
            $created = $this->proposals->createFromInsights($session, $elementId, $evidence->toArray(), (int) $user->getKey());
        } catch (ProposalException $e) {
            return $this->fail($e->getMessage(), $e->status, (string) ($e->extra['code'] ?? 'refused'));
        }
        $proposal = $created['proposal'];
        if ($created['state'] === 'kept_existing') {
            return response()->json(['data' => ['state' => 'kept_existing', 'proposal' => Presenter::proposalSummary($proposal)]]);
        }
        $result = $this->analysis->begin($proposal, true, (int) $user->getKey());
        $body = ['state' => $result['state'], 'runId' => $result['run']?->id, 'estimateMicroUsd' => $result['estimateMicroUsd'], 'message' => $result['message'], 'proposal' => Presenter::proposalSummary($proposal->refresh())];

        return $result['state'] === 'budget_blocked'
            ? response()->json(['success' => false, 'code' => 'budget_blocked', 'message' => $result['message'], 'data' => $body], 422)
            : response()->json(['data' => $body], 201);
    }

    private function fail(string $message, int $status, string $code): JsonResponse
    {
        return response()->json(['success' => false, 'code' => $code, 'message' => $message], $status);
    }
}
