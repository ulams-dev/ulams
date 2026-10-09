<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\LivingCourse\Diff\ImpactAnalyzer;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Services\AnalysisService;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\RevisionFragment;
use Ulams\LivingCourse\Support\Presenter;

/**
 * Update proposals: list and detail (decisions, analysis and apply are added by their own endpoints).
 *
 * @OA\Get(path="/api/admin/living-course/sessions/{session}/proposals", summary="Update proposals of a session, newest first", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="proposals"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown session"))
 * @OA\Get(path="/api/admin/living-course/proposals/{proposal}", summary="A proposal with its items grouped by lesson, citations and source changes", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="proposal"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown proposal"))
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/analyse", summary="Start or resume the AI analysis (confirmEstimate when above the automatic threshold)", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="confirmEstimate", type="boolean"))),
 *     @OA\Response(response=202, description="analysis started"), @OA\Response(response=409, description="estimate must be confirmed"), @OA\Response(response=422, description="budget blocked"), @OA\Response(response=503, description="AI disabled"))
 */
class ProposalsController extends Controller
{
    use ResolvesLivingCourse;

    public function __construct(private readonly AnalysisService $analysis, private readonly LlmClient $llm)
    {
    }

    public function index(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session);

        return self::ok(Proposal::query()->where('session_id', $s->id)->orderByDesc('number')->get()->map(fn (Proposal $p) => Presenter::proposalSummary($p))->all());
    }

    public function show(Request $request, string $proposal): JsonResponse
    {
        [$s, $p] = $this->proposalFor($request, $proposal);

        return self::ok($this->detail($s, $p));
    }

    public function analyse(Request $request, string $proposal): JsonResponse
    {
        [, $p] = $this->proposalFor($request, $proposal, 'act');
        if (!$this->llm->enabled()) {
            return response()->json(['success' => false, 'message' => 'AI analysis is disabled on this installation. The changed elements are marked for you to update by hand.', 'code' => 'ai_disabled'], 503);
        }
        if (!in_array($p->status, ['awaiting_analysis', 'budget_blocked', 'ready', 'failed'], true)) {
            return self::fail('This proposal cannot be analysed now.', 409);
        }
        $result = $this->analysis->begin($p, $request->boolean('confirmEstimate'), (int) $request->user()->getKey());
        $body = ['state' => $result['state'], 'estimateMicroUsd' => $result['estimateMicroUsd'], 'runId' => $result['run']?->id, 'message' => $result['message']];

        return match ($result['state']) {
            'needs_confirmation' => response()->json(['success' => false, 'message' => $result['message'], 'code' => 'confirm_estimate', 'data' => $body], 409),
            'budget_blocked' => response()->json(['success' => false, 'message' => $result['message'], 'code' => 'budget_blocked', 'data' => $body], 422),
            default => self::ok($body, 202),
        };
    }

    /** @return array{0:Session,1:Proposal} */
    protected function proposalFor(Request $request, ?string $id, string $ability = 'view'): array
    {
        $p = self::isUlid($id) ? Proposal::query()->find(strtolower((string) $id)) : null;
        if ($p === null) {
            throw new NotFoundHttpException('Proposal not found.');
        }

        return [$this->sessionFor($request, $p->session_id, $ability), $p];
    }

    /** @return array<string,mixed> */
    protected function detail(Session $session, Proposal $p): array
    {
        $labels = [];
        foreach ([$p->from_revision_id, $p->to_revision_id] as $revisionId) {
            foreach (RevisionFragment::query()->where('revision_id', $revisionId)->get(['fragment_id', 'section', 'heading_path', 'file_path']) as $f) {
                $labels[$f->fragment_id] ??= $f->label();
            }
        }
        $base = $p->base_version_id ? Version::query()->find($p->base_version_id) : null;
        $order = $base ? array_flip(array_keys((new ImpactAnalyzer())->elements($base->document))) : [];
        $groupLabels = $this->groupLabels($base?->document ?? []);
        $items = $p->items()->get()->sortBy(fn (ProposalItem $i) => sprintf('%s-%05d', $i->kind === 'uncovered' ? 'z' : 'a', $order[$i->element_id] ?? 99999))->values();

        $groups = [];
        foreach ($items as $i) {
            $groups[$i->group_key] ??= ['key' => $i->group_key, 'label' => $groupLabels[$i->group_key] ?? ($i->group_key === 'uncovered' ? 'New in the source' : $i->group_key), 'items' => []];
            $groups[$i->group_key]['items'][] = Presenter::item($i, $labels);
        }

        return Presenter::proposalSummary($p) + [
            'groups' => array_values($groups),
            'items' => $items->map(fn (ProposalItem $i) => Presenter::item($i, $labels))->all(),
        ];
    }

    /** @return array<string,string> group key => heading */
    private function groupLabels(array $doc): array
    {
        $out = ['course' => 'Course', 'final_test' => 'Final test'];
        foreach (Blueprint::lessons($doc) as $item) {
            $out['lesson:' . $item['lesson']['id']] = "Lesson {$item['number']}: {$item['lesson']['title']}";
        }

        return $out;
    }
}
