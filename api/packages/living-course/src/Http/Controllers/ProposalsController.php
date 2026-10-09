<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\LivingCourse\Diff\ImpactAnalyzer;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Services\AnalysisService;
use Ulams\LivingCourse\Services\ApplyService;
use Ulams\LivingCourse\Services\DecisionService;
use Ulams\LivingCourse\Services\AuditLog;
use Ulams\LivingCourse\Services\ProgressRules;
use Ulams\LivingCourse\Services\ProposalService;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
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
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/items/{item}/accept", summary="Accept one item", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="item", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the item"), @OA\Response(response=403, description="not allowed"), @OA\Response(response=404, description="unknown"), @OA\Response(response=409, description="not decidable now"))
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/items/{item}/reject", summary="Reject one item (keep the earlier version of the element)", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="item", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the item"), @OA\Response(response=403, description="not allowed"), @OA\Response(response=404, description="unknown"), @OA\Response(response=409, description="not decidable now"))
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/items/{item}/reset", summary="Back to undecided", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="item", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the item"), @OA\Response(response=403, description="not allowed"), @OA\Response(response=404, description="unknown"), @OA\Response(response=409, description="not decidable now"))
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/items/{item}/regenerate", summary="Ask for another version of one element ({comment}); one call, at most three per item", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="item", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the item"), @OA\Response(response=403, description="not allowed"), @OA\Response(response=404, description="unknown"), @OA\Response(response=409, description="not decidable now"))
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/accept-all", summary="Accept every undecided update, no-change and removal item", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the proposal"), @OA\Response(response=403, description="not allowed"), @OA\Response(response=409, description="already settled"))
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/reject", summary="Reject the whole proposal and acknowledge the source revision", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the proposal"), @OA\Response(response=403, description="not allowed"), @OA\Response(response=409, description="already settled"))
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/reanalyse", summary="Replace the proposal by a new one from the newest source revision", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=201, description="the new proposal"), @OA\Response(response=409, description="already settled"))
 * @OA\Put(path="/api/admin/living-course/proposals/{proposal}/learner-note", summary="The text learners see for updated lessons (plain text, up to 500 characters)", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="note", type="string"))),
 *     @OA\Response(response=200, description="note"), @OA\Response(response=409, description="already settled"))
 * @OA\Post(path="/api/admin/living-course/proposals/{proposal}/apply", summary="Apply the accepted items as a new course version (409 with conflicting items or admin edits)", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="overwrite", type="boolean"))),
 *     @OA\Response(response=202, description="apply run"), @OA\Response(response=409, description="conflicts or admin edits"))
 */
class ProposalsController extends Controller
{
    use ResolvesLivingCourse;

    public function __construct(private readonly AnalysisService $analysis, private readonly LlmClient $llm, private readonly DecisionService $decisions, private readonly ApplyService $applying, private readonly ProgressRules $rules, private readonly ProposalService $proposals, private readonly AuditLog $audit)
    {
    }

    public function accept(Request $request, string $proposal, string $item): JsonResponse
    {
        return $this->decide($request, $proposal, $item, 'accepted');
    }

    public function reject(Request $request, string $proposal, string $item): JsonResponse
    {
        return $this->decide($request, $proposal, $item, 'rejected');
    }

    public function reset(Request $request, string $proposal, string $item): JsonResponse
    {
        return $this->decide($request, $proposal, $item, 'pending');
    }

    private function decide(Request $request, string $proposal, string $item, string $decision): JsonResponse
    {
        [, $p, $i] = $this->itemFor($request, $proposal, $item);
        try {
            $i = $this->decisions->decide($p, $i, $decision, (int) $request->user()->getKey());
        } catch (ProposalException $e) {
            return self::fail($e->getMessage(), $e->status, $e->extra);
        }

        return self::ok(['item' => Presenter::item($i), 'proposal' => Presenter::proposalSummary($p->refresh())]);
    }

    public function regenerate(Request $request, string $proposal, string $item): JsonResponse
    {
        [, $p, $i] = $this->itemFor($request, $proposal, $item);
        $request->validate(['comment' => ['nullable', 'string', 'max:1000']]);
        if (!$this->llm->enabled()) {
            return response()->json(['success' => false, 'message' => 'AI is disabled on this installation. Edit the element by hand in the workspace.', 'code' => 'ai_disabled'], 503);
        }
        try {
            $i = $this->decisions->regenerate($p, $i, (string) $request->input('comment', ''), (int) $request->user()->getKey());
        } catch (ProposalException $e) {
            return self::fail($e->getMessage(), $e->status, $e->extra);
        } catch (LlmException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => $e->reason], $e->reason === LlmException::BUDGET ? 422 : 502);
        }

        return self::ok(['item' => Presenter::item($i), 'proposal' => Presenter::proposalSummary($p->refresh())]);
    }

    /** Starts again from the newest revision; decisions already made are not carried over (plan 8.3). */
    public function reanalyse(Request $request, string $proposal): JsonResponse
    {
        [$session, $p] = $this->proposalFor($request, $proposal, 'act');
        if (!$p->isOpen()) {
            return self::fail('This proposal is already settled.', 409);
        }
        $connection = Connection::query()->where('source_id', $p->source_id)->firstOrFail();
        $latest = Revision::query()->findOrFail($connection->latest_revision_id);
        $p->forceFill(['status' => 'superseded'])->save();
        $this->audit->record('proposal.superseded', [
            'session_id' => $session->id, 'subject_type' => 'proposal', 'subject_id' => $p->id, 'source_id' => $p->source_id, 'revision_id' => $latest->id,
            'actor_id' => (int) $request->user()->getKey(), 'data' => ['number' => $p->number, 'reanalysed' => true, 'by_revision' => $latest->number],
        ]);
        $created = $this->proposals->createFor($latest, 'manual', (int) $request->user()->getKey());

        return self::ok(['state' => $created['state'], 'proposal' => $created['proposal'] ? Presenter::proposalSummary($created['proposal']) : null], 201);
    }

    public function learnerNote(Request $request, string $proposal): JsonResponse
    {
        [, $p] = $this->proposalFor($request, $proposal, 'act');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        if (!in_array($p->status, Proposal::OPEN, true)) {
            return self::fail('This proposal is already settled.', 409);
        }
        $p->forceFill(['learner_note' => trim((string) ($data['note'] ?? '')) ?: null])->save();

        return self::ok(['learnerNote' => $p->learner_note, 'effectiveNote' => $this->rules->note($p)]);
    }

    public function apply(Request $request, string $proposal): JsonResponse
    {
        [, $p] = $this->proposalFor($request, $proposal, 'act');
        if ($p->status !== 'ready') {
            return self::fail($p->status === 'applied' ? 'This proposal is already applied.' : 'This proposal is not ready to apply.', 409);
        }
        try {
            $preview = $this->applying->preview($p);
        } catch (ProposalException $e) {
            return self::fail($e->getMessage(), $e->status);
        }
        if ($preview['conflicts'] !== []) {
            return response()->json(['success' => false, 'code' => 'conflicts', 'message' => 'You edited some elements after the analysis. Ask for a new version of them, or reject them, then apply again.',
                'data' => ['conflicts' => array_map(fn ($i) => Presenter::item($i), $preview['conflicts'])]], 409);
        }
        if ($preview['drift'] !== [] && !$request->boolean('overwrite')) {
            return response()->json(['success' => false, 'code' => 'admin_edits', 'message' => 'These elements were edited in the admin after the last apply: ' . implode(', ', array_slice(array_values($preview['drift']), 0, 5)) . '. Applying overwrites those edits; confirm to continue.',
                'data' => ['drift' => array_values($preview['drift'])]], 409);
        }
        $run = $this->applying->start($p, (int) $request->user()->getKey(), $request->boolean('overwrite'));

        return self::ok(['runId' => $run->id, 'proposal' => Presenter::proposalSummary($p->refresh())], 202);
    }

    public function acceptAll(Request $request, string $proposal): JsonResponse
    {
        [, $p] = $this->proposalFor($request, $proposal, 'act');
        try {
            $count = $this->decisions->acceptAll($p, (int) $request->user()->getKey());
        } catch (ProposalException $e) {
            return self::fail($e->getMessage(), $e->status, $e->extra);
        }

        return self::ok(['accepted' => $count, 'proposal' => Presenter::proposalSummary($p->refresh())]);
    }

    public function rejectAll(Request $request, string $proposal): JsonResponse
    {
        [, $p] = $this->proposalFor($request, $proposal, 'act');
        try {
            $this->decisions->rejectProposal($p, (int) $request->user()->getKey());
        } catch (ProposalException $e) {
            return self::fail($e->getMessage(), $e->status, $e->extra);
        }

        return self::ok(Presenter::proposalSummary($p->refresh()));
    }

    /** @return array{0:Session,1:Proposal,2:ProposalItem} */
    protected function itemFor(Request $request, string $proposal, string $item): array
    {
        [$s, $p] = $this->proposalFor($request, $proposal, 'act');
        $i = self::isUlid($item) ? ProposalItem::query()->where('proposal_id', $p->id)->find(strtolower($item)) : null;
        if ($i === null) {
            throw new NotFoundHttpException('Item not found.');
        }

        return [$s, $p, $i];
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
            'steps' => $p->run_id ? \Ulams\CourseBuilder\Models\Step::query()->where('run_id', $p->run_id)->orderBy('key')->get()->map(fn ($st) => ['id' => $st->id, 'groupKey' => str_starts_with($st->key, 'group:') ? substr($st->key, 6) : $st->key, 'status' => $st->status, 'error' => $st->error])->all() : [],
            'learnerImpact' => ['learners' => $this->rules->impact($p, $p->status === 'applied' ? ['accepted'] : ['accepted']), 'note' => $this->rules->note($p)],
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
