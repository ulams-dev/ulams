<?php

namespace Ulams\LivingCourse\Progress;

use Ulams\CourseBuilder\Contracts\PendingChanges;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;

/** Open update proposals and the elements their undecided items would change (plan 9.5). */
final class LivingCoursePendingChanges implements PendingChanges
{
    /** Item kinds that change or remove a course element; `no_change` and `uncovered` do not. */
    private const CHANGING = ['update', 'citation_remap', 'remove', 'manual'];

    public function forSession(Session $session): array
    {
        $out = [];
        foreach (Proposal::query()->where('session_id', $session->id)->whereIn('status', Proposal::OPEN)->orderBy('number')->get() as $proposal) {
            $ids = ProposalItem::query()->where('proposal_id', $proposal->id)->whereIn('status', ['pending', 'stale', 'conflict'])->whereIn('kind', self::CHANGING)
                ->pluck('element_id')->unique()->values()->all();
            if ($ids !== []) {
                $out[] = ['id' => $proposal->id, 'number' => (int) $proposal->number, 'elementIds' => $ids];
            }
        }

        return $out;
    }
}
