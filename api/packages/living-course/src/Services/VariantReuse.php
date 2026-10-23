<?php

namespace Ulams\LivingCourse\Services;

use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;

/**
 * One analysis per source change for all variants (ADR 0192, CV-5): when the base proposal's analysis has finished, every
 * variant element that was marked as inheriting (word for word the base's) takes the base's patch, with no model call. What is
 * left in a variant proposal is what only that variant changed; it is analysed with the variant's audience, automatically when
 * the source is set to analyse by itself, else when the author asks like for any proposal.
 */
final class VariantReuse
{
    public function __construct(private readonly AnalysisService $analysis)
    {
    }

    /** @return int how many variant items were filled from the base */
    public function reuse(Proposal $base): int
    {
        if ($base->proposal_group === null || $base->variant_id !== null) {
            return 0;
        }
        $baseItems = ProposalItem::query()->where('proposal_id', $base->id)->whereIn('kind', ['update', 'no_change', 'remove'])->get()->keyBy('id');
        $copied = 0;
        $variants = Proposal::query()->where('proposal_group', $base->proposal_group)->whereNotNull('variant_id')->whereIn('status', Proposal::OPEN)->get();
        foreach ($variants as $proposal) {
            $filled = 0;
            foreach (ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'manual')->get() as $item) {
                $from = $baseItems[$item->flags['inherits'] ?? ''] ?? null;
                if ($from === null) {
                    continue;
                }
                $flags = (array) $from->flags;
                unset($flags['signals']);
                $item->forceFill([
                    'kind' => $from->kind, 'reason' => $from->reason, 'severity' => $from->severity,
                    'fragment_ids' => $from->fragment_ids ?: $item->fragment_ids, 'after' => $from->after, 'change_class' => $from->change_class,
                    'answer_status' => $from->answer_status, 'status' => 'pending',
                    // no model call was made for this variant: the cost stays on the base proposal, the item says where it came from
                    'flags' => array_merge((array) $item->flags, $flags, ['reusedFrom' => $from->id]),
                ])->save();
                $filled++;
            }
            $copied += $filled;
            $this->settle($proposal, $filled);
        }

        return $copied;
    }

    private function settle(Proposal $proposal, int $filled): void
    {
        $left = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'manual')->get();
        $divergent = $left->filter(fn (ProposalItem $i) => empty($i->flags['inherits']))->count();
        $counts = (array) $proposal->counts;
        $counts['reused'] = ($counts['reused'] ?? 0) + $filled;
        $counts['analysed'] = ProposalItem::query()->where('proposal_id', $proposal->id)->whereIn('kind', ['update', 'no_change', 'remove'])->count();
        $proposal->forceFill(['counts' => $counts, 'status' => $divergent === 0 ? 'ready' : $proposal->status])->save();
        if ($divergent > 0 && $proposal->status === 'awaiting_analysis') {
            $connection = Connection::query()->where('source_id', $proposal->source_id)->first();
            if ($connection?->auto_analyse) {
                $this->analysis->begin($proposal, false, $proposal->created_by);
            }
        }
    }
}
