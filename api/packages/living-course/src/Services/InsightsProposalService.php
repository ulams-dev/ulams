<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\LivingCourse\Analysis\Evidence;
use Ulams\LivingCourse\Analysis\ImproveRequest;
use Ulams\LivingCourse\Diff\ImpactAnalyzer;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;

/**
 * Improvement proposals from learner insights (ADR 0181): a proposal of its own kind, with no source
 * revision, created from aggregated evidence about one element the learners struggle with. The model
 * only proposes: the items start as `manual`, `AnalysisService` turns them into reviewable patches and
 * nothing reaches the course before the author accepts and applies it.
 *
 * Refused here, whoever calls: evidence from a group below the minimum, an element that is not a block,
 * a quiz question or a lesson of the current approved version, an element that cites nothing (it could
 * not be grounded) and a fourth proposal of a session in one day. An open proposal for the same element
 * is returned instead of a second one.
 */
final class InsightsProposalService
{
    /** Element types a proposal may rewrite; a lesson stands for its text blocks. */
    public const TYPES = ['block', 'question', 'lesson'];

    public function __construct(
        private readonly AuditLog $audit,
        private readonly AnalysisService $analysis,
        private readonly PromptContext $context,
        private readonly LlmClient $llm,
    ) {
    }

    /** An improvement is written by the model: with AI switched off there is nothing to propose. */
    public function assertAiEnabled(): void
    {
        if (!$this->llm->enabled()) {
            throw new ProposalException('AI is disabled on this installation. Edit the element by hand in the workspace.', 503, ['code' => 'ai_disabled']);
        }
    }

    /**
     * What a proposal would cost, without creating one.
     *
     * @param array<string,mixed> $evidence
     * @return array{estimateMicroUsd:int,elements:int,label:string}
     */
    public function estimate(Session $session, string $elementId, array $evidence): array
    {
        $current = $this->currentVersion($session);
        $evidence = Evidence::sanitise($evidence);
        $target = $this->target($session, $current, $elementId);
        $items = array_map(fn (array $spec) => new ProposalItem($spec + ['id' => $spec['element_id']]), $target['items']);
        $request = new ImproveRequest($current, $session, $this->context, $evidence);

        return ['estimateMicroUsd' => $this->analysis->estimateRequest($request, 'insights_improve', 'update-improve', $items, $target['group']), 'elements' => count($items), 'label' => $target['label']];
    }

    /**
     * @param array<string,mixed> $evidence aggregated, see `Evidence::sanitise()`
     * @return array{proposal:Proposal,state:string} state: created | kept_existing
     */
    public function create(Session $session, string $elementId, array $evidence, int $userId): array
    {
        $current = $this->currentVersion($session);
        $evidence = Evidence::sanitise($evidence);
        $target = $this->target($session, $current, $elementId);

        $open = $this->openFor($session, $elementId);
        if ($open !== null) {
            return ['proposal' => $open, 'state' => 'kept_existing'];
        }
        $limit = (int) config('living_course.cost.insights_proposals_per_session_per_day', 3);
        $today = Proposal::query()->where('session_id', $session->id)->where('kind', Proposal::KIND_INSIGHTS)->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= $limit) {
            throw new ProposalException(sprintf('This course already has %d improvement proposals from learner insights today. Review them, or try again tomorrow.', $limit), 429, ['code' => 'daily_limit']);
        }

        $proposal = DB::transaction(function () use ($session, $current, $target, $evidence, $userId, $elementId) {
            Session::query()->whereKey($session->id)->lockForUpdate()->first(['id']);
            $number = (int) Proposal::query()->where('session_id', $session->id)->max('number') + 1;
            $counts = [
                'items' => count($target['items']), 'elements' => count($target['items']), 'remaps' => 0, 'uncovered' => 0, 'major' => count($target['items']), 'answerChecks' => 0,
                'groups' => 1, 'byType' => array_count_values(array_column($target['items'], 'element_type')), 'targetElementId' => $elementId, 'targetLabel' => $target['label'],
            ];
            $proposal = Proposal::query()->create([
                'number' => $number,
                'kind' => Proposal::KIND_INSIGHTS,
                'session_id' => $session->id,
                'base_version_id' => $current->id,
                'status' => 'awaiting_analysis',
                'trigger' => 'insights',
                'created_by' => $userId,
                'counts' => $counts,
                'evidence' => $evidence,
            ]);
            $reason = Evidence::sentence($evidence);
            foreach ($target['items'] as $spec) {
                ProposalItem::query()->create($spec + ['proposal_id' => $proposal->id, 'reason' => $reason]);
            }
            $this->audit->record('proposal.created', [
                'session_id' => $session->id, 'subject_type' => 'proposal', 'subject_id' => $proposal->id, 'version_from' => $current->number, 'actor_id' => $userId,
                'data' => ['number' => $number, 'kind' => Proposal::KIND_INSIGHTS, 'status' => $proposal->status, 'element' => $elementId, 'label' => $target['label'], 'evidence' => Evidence::summary($evidence)],
            ]);

            return $proposal;
        });

        return ['proposal' => $proposal, 'state' => 'created'];
    }

    /** The undecided insights proposal of an element, if any: one per element at a time. */
    public function openFor(Session $session, string $elementId): ?Proposal
    {
        return Proposal::query()->where('session_id', $session->id)->where('kind', Proposal::KIND_INSIGHTS)->whereIn('status', Proposal::OPEN)
            ->where('counts->targetElementId', $elementId)->orderBy('number')->first();
    }

    private function currentVersion(Session $session): Version
    {
        $current = $session->currentVersion;
        if ($current === null || $current->status !== Version::APPROVED || !in_array($current->kind, VersionService::CONTENT_KINDS, true)) {
            throw new ProposalException('This course has no approved version yet, so there is nothing to improve.', 409, ['code' => 'no_course']);
        }

        return $current;
    }

    /**
     * The items a proposal for `$elementId` holds, the group they are analysed in and the passages they cite.
     *
     * @return array{items:array<int,array<string,mixed>>,group:string,label:string}
     */
    private function target(Session $session, Version $current, string $elementId): array
    {
        $elements = (new ImpactAnalyzer())->elements($current->document);
        $el = $elements[$elementId] ?? null;
        if ($el === null || !in_array($el['type'], self::TYPES, true)) {
            throw new ProposalException('This part of the course cannot be improved from learner insights. Only text blocks, quiz questions and lessons built with the Course Builder can.', 422, ['code' => 'element_not_improvable']);
        }
        $ids = $el['type'] === 'lesson'
            ? array_slice(array_keys(array_filter($elements, fn ($e) => $e['type'] === 'block' && $e['group'] === $el['group'])), 0, max(1, (int) config('living_course.insights.max_blocks_per_lesson', 4)))
            : [$elementId];
        $live = $this->context->fragmentMap($session);
        $items = [];
        foreach ($ids as $id) {
            $cited = array_values(array_filter(ImproveRequest::citedIds($elements, $id, $elements[$id]['group']), fn ($f) => isset($live[$f])));
            if ($cited === []) {
                continue;
            }
            $e = $elements[$id];
            $items[] = [
                'group_key' => $e['group'], 'element_id' => $id, 'element_type' => $e['type'], 'label' => $e['label'], 'kind' => 'manual',
                'fragment_ids' => $cited, 'severity' => 'major', 'before' => $e['node'], 'status' => 'pending', 'answer_check' => false,
            ];
        }
        if ($items === []) {
            throw new ProposalException('This part of the course cites no passage of the source material, so an improvement could not be grounded in it. Edit it by hand in the workspace.', 422, ['code' => 'no_sources']);
        }

        return ['items' => $items, 'group' => $items[0]['group_key'], 'label' => $el['label']];
    }
}
