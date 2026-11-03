<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\StyleProfile\StyleProfileResolver;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Services\AnalysisService;
use Ulams\LivingCourse\Services\StyleProposalService;
use Ulams\LivingCourse\Support\Presenter;

/**
 * Restyle proposals (ADR 0196): after a style profile changes, an optional proposal that brings the flagged elements of a
 * course in line with it. Without `confirmEstimate` it only says what would be rewritten and what it would cost; with it, a
 * proposal of kind `style` is created and analysed. The author reviews and applies the diff on the Living Course review
 * screen; nothing changes in the course before that, and a restyle is never started by itself.
 *
 * @OA\Post(path="/api/admin/living-course/sessions/{session}/style-proposals", operationId="admin.living-course.sessions.by-session.style-proposals.post", summary="Propose a restyle of the course for an approved style profile (default: the one the brief names, else the academy's). Without confirmEstimate: the estimate only", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="styleProfileId", type="string"), @OA\Property(property="confirmEstimate", type="boolean"))),
 *     @OA\Response(response=200, description="state estimate (what and how much) or kept_existing", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=201, description="proposal created and analysis started", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="not the author"), @OA\Response(response=404, description="unknown session or profile"), @OA\Response(response=409, description="no approved version"), @OA\Response(response=422, description="profile not approved, nothing to restyle, or budget"), @OA\Response(response=429, description="daily limit"), @OA\Response(response=503, description="AI disabled"))
 */
class StyleProposalsController extends Controller
{
    use ResolvesLivingCourse;

    public function __construct(
        private readonly StyleProposalService $styles,
        private readonly StyleProfileResolver $resolver,
        private readonly AnalysisService $analysis,
    ) {
    }

    public function store(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'act');
        $data = $request->validate(['styleProfileId' => ['nullable', 'string', 'max:26'], 'confirmEstimate' => ['sometimes', 'boolean']]);
        $profile = isset($data['styleProfileId']) && $data['styleProfileId'] !== ''
            ? (preg_match('/^[0-9a-z]{26}$/', strtolower($data['styleProfileId'])) ? StyleProfile::query()->find(strtolower($data['styleProfileId'])) : null)
            : $this->resolver->profileFor($s);
        if ($profile === null) {
            throw new NotFoundHttpException('There is no style profile to restyle with: approve one first.');
        }
        try {
            $this->styles->assertAiEnabled();
            if (!$request->boolean('confirmEstimate')) {
                $existing = $this->styles->openFor($s);
                if ($existing !== null) {
                    return response()->json(['data' => ['state' => 'kept_existing', 'proposal' => Presenter::proposalSummary($existing)]]);
                }
                $estimate = $this->styles->estimate($s, $profile);

                return response()->json(['data' => [
                    'state' => 'estimate', 'profile' => ['id' => $profile->id, 'name' => $profile->name],
                    'estimateMicroUsd' => $estimate['estimateMicroUsd'], 'estimateUsd' => round($estimate['estimateMicroUsd'] / 1e6, 4),
                    'elements' => $estimate['elements'], 'findings' => $estimate['findings'], 'skipped' => $estimate['skipped'], 'answerKeys' => $estimate['answerKeys'],
                ]]);
            }
            $created = $this->styles->create($s, $profile, (int) $request->user()->getKey());
        } catch (ProposalException $e) {
            return response()->json(['success' => false, 'code' => (string) ($e->extra['code'] ?? 'refused'), 'message' => $e->getMessage()], $e->status);
        }
        $proposal = $created['proposal'];
        if ($created['state'] === 'kept_existing') {
            return response()->json(['data' => ['state' => 'kept_existing', 'proposal' => Presenter::proposalSummary($proposal)]]);
        }
        $result = $this->analysis->begin($proposal, true, (int) $request->user()->getKey());
        $body = ['state' => $result['state'], 'runId' => $result['run']?->id, 'estimateMicroUsd' => $result['estimateMicroUsd'], 'message' => $result['message'], 'proposal' => Presenter::proposalSummary($proposal->refresh())];

        return $result['state'] === 'budget_blocked'
            ? response()->json(['success' => false, 'code' => 'budget_blocked', 'message' => $result['message'], 'data' => $body], 422)
            : response()->json(['data' => $body], 201);
    }
}
