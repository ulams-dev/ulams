<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Analysis\UpdateRequest;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\Revision;
use Ulams\CourseBuilder\Pipeline\PromptContext;

/**
 * The author's decisions on a proposal (plan 8.3): per item accept, reject, reset or ask for
 * another version; accept all; reject the whole proposal, which acknowledges the revision so the
 * same changes are not proposed again. Every decision is audited.
 */
final class DecisionService
{
    /** Item kinds that carry something the author decides (remaps are accepted by default, uncovered is informational). */
    public const DECIDABLE = ['update', 'no_change', 'remove', 'manual', 'citation_remap', 'uncovered'];

    public function __construct(
        private readonly AuditLog $audit,
        private readonly AnalysisService $analysis,
        private readonly RevisionService $revisions,
        private readonly StalenessService $staleness,
        private readonly PromptContext $context,
    ) {
    }

    private function assertOpen(Proposal $proposal): void
    {
        if (!in_array($proposal->status, ['ready', 'awaiting_analysis', 'analysing', 'budget_blocked', 'failed'], true)) {
            throw new ProposalException('This proposal is already settled.', 409);
        }
    }

    public function decide(Proposal $proposal, ProposalItem $item, string $decision, int $userId): ProposalItem
    {
        $this->assertOpen($proposal);
        if (!in_array($decision, ['accepted', 'rejected', 'pending'], true)) {
            throw new ProposalException('Unknown decision.', 422);
        }
        if ($decision === 'accepted' && $item->status === 'conflict') {
            throw new ProposalException('You edited this element after the analysis. Ask for a new version of it, or reject it.', 409);
        }
        $item->forceFill([
            'status' => $decision,
            'decided_by' => $decision === 'pending' ? null : $userId,
            'decided_at' => $decision === 'pending' ? null : now(),
        ])->save();
        $this->audit->record(['accepted' => 'item.accepted', 'rejected' => 'item.rejected', 'pending' => 'item.reset'][$decision], $this->context($proposal, $item, $userId));

        return $item->refresh();
    }

    /** @return int number of items accepted */
    public function acceptAll(Proposal $proposal, int $userId): int
    {
        $this->assertOpen($proposal);
        $count = 0;
        DB::transaction(function () use ($proposal, $userId, &$count) {
            foreach (ProposalItem::query()->where('proposal_id', $proposal->id)->where('status', 'pending')->whereIn('kind', ['update', 'no_change', 'remove'])->get() as $item) {
                $item->forceFill(['status' => 'accepted', 'decided_by' => $userId, 'decided_at' => now()])->save();
                $this->audit->record('item.accepted', $this->context($proposal, $item, $userId) + []);
                $count++;
            }
        });

        return $count;
    }

    /** Rejects the whole proposal and acknowledges the revision (decision 4). */
    public function rejectProposal(Proposal $proposal, int $userId): void
    {
        $this->assertOpen($proposal);
        $session = Session::query()->findOrFail($proposal->session_id);
        DB::transaction(function () use ($proposal, $session, $userId) {
            $proposal->forceFill(['status' => 'rejected', 'decided_by' => $userId, 'decided_at' => now()])->save();
            ProposalItem::query()->where('proposal_id', $proposal->id)->whereIn('status', ['pending', 'accepted', 'conflict', 'stale'])->where('kind', '!=', 'citation_remap')
                ->update(['status' => 'rejected', 'decided_by' => $userId, 'decided_at' => now()]);
            $this->staleness->settleOpen($session, 'dismissed');
            $this->revisions->promote(Revision::query()->findOrFail($proposal->to_revision_id), $userId);
            $this->audit->record('proposal.rejected', [
                'session_id' => $session->id, 'subject_type' => 'proposal', 'subject_id' => $proposal->id, 'source_id' => $proposal->source_id,
                'revision_id' => $proposal->to_revision_id, 'actor_id' => $userId, 'data' => ['number' => $proposal->number, 'acknowledged_revision' => $proposal->toRevision?->number],
            ]);
        });
    }

    /** One new call for one element, with the author's comment (max 3 per item, capped by the proposal budget). */
    public function regenerate(Proposal $proposal, ProposalItem $item, string $comment, int $userId): ProposalItem
    {
        $this->assertOpen($proposal);
        if (!in_array($item->kind, ['update', 'no_change', 'remove', 'manual'], true)) {
            throw new ProposalException('This item has nothing to regenerate.', 409);
        }
        if ($item->regenerations >= (int) config('living_course.cost.regenerations_per_item', 3)) {
            throw new ProposalException('You asked for a new version of this element three times already. Edit it by hand in the workspace.', 422);
        }
        $session = Session::query()->findOrFail($proposal->session_id);
        $request = new UpdateRequest($proposal, $session, $this->context);
        $item->forceFill(['regenerations' => $item->regenerations + 1])->save();
        // analyseItems writes the new answer into the item; manual items become analysed items
        $this->analysis->analyseItems($proposal, $request, $item->group_key, [$item], mb_substr(trim($comment), 0, 1000) ?: 'Please try again.');
        $this->analysis->syncCost($proposal);
        $item->refresh()->forceFill(['status' => 'pending', 'decided_by' => null, 'decided_at' => null])->save();
        $this->audit->record('item.regenerated', $this->context($proposal, $item, $userId) + ['ai_call_ids' => array_slice((array) $item->ai_call_ids, -6)]);

        return $item->refresh();
    }

    /** @return array<string,mixed> */
    private function context(Proposal $proposal, ProposalItem $item, int $userId): array
    {
        return [
            'session_id' => $proposal->session_id, 'subject_type' => 'proposal_item', 'subject_id' => $item->id, 'source_id' => $proposal->source_id,
            'revision_id' => $proposal->to_revision_id, 'actor_id' => $userId,
            'data' => ['proposal' => $proposal->number, 'element' => $item->element_id, 'label' => $item->label, 'kind' => $item->kind, 'changeClass' => $item->change_class, 'answerStatus' => $item->answer_status],
        ];
    }
}
