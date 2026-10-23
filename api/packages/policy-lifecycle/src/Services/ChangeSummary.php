<?php

namespace Ulams\PolicyLifecycle\Services;

use Ulams\LivingCourse\Models\FragmentChange;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;

/**
 * The source diff of a policy change as the notices and the review screen tell it: built from the stored fragment
 * changes between two revisions of the policy file, never generated, so it needs no citation check beyond linking the
 * fragments (ADR 0140).
 */
final class ChangeSummary
{
    private const KINDS = ['changed' => 'changed', 'moved' => 'moved', 'added' => 'added', 'removed' => 'removed'];

    /**
     * @return array{counts:array<string,int>,sections:array<int,array{kind:string,label:string,fragmentId:?string,magnitude:string}>}
     */
    public function of(?Revision $to): array
    {
        $counts = ['changed' => 0, 'moved' => 0, 'added' => 0, 'removed' => 0];
        $sections = [];
        if ($to === null) {
            return ['counts' => $counts, 'sections' => []];
        }
        $changes = FragmentChange::query()->where('to_revision_id', $to->id)->orderBy('id')->get();
        foreach ($changes as $change) {
            $kind = self::KINDS[$change->kind] ?? 'changed';
            $counts[$kind]++;
            if ($kind === 'moved' && $change->magnitude === 'trivial') {
                continue;
            }
            $revision = $kind === 'removed' ? $change->from_revision_id : $to->id;
            $fragmentId = $kind === 'removed' ? $change->old_fragment_id : $change->new_fragment_id;
            $label = RevisionFragment::query()->where('revision_id', $revision)->where('fragment_id', $fragmentId)->first()?->label() ?? (string) $fragmentId;
            $sections[] = ['kind' => $kind, 'label' => $label, 'fragmentId' => $fragmentId, 'magnitude' => (string) $change->magnitude];
        }

        return ['counts' => $counts, 'sections' => $sections];
    }

    /** One short, factual sentence for the notice and the version row, e.g. "Sections 2 changed, 1 added: §1.1 Approval; §2.1 Data". */
    public function sentence(array $summary): string
    {
        $parts = [];
        foreach (['changed' => 'changed', 'added' => 'added', 'removed' => 'removed'] as $kind => $word) {
            $n = $summary['counts'][$kind] ?? 0;
            if ($n > 0) {
                $parts[] = $n . ' ' . $word;
            }
        }
        $head = $parts === [] ? 'No section of the policy text changed' : 'Sections ' . implode(', ', $parts);
        $labels = array_values(array_unique(array_column(array_slice($summary['sections'], 0, 6), 'label')));

        return mb_substr($head . ($labels !== [] ? ': ' . implode('; ', $labels) : '') . '.', 0, 900);
    }
}
