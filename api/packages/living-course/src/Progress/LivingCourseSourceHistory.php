<?php

namespace Ulams\LivingCourse\Progress;

use Ulams\CourseBuilder\Contracts\SourceHistory;
use Ulams\LivingCourse\Models\FragmentChange;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;

/** The fragment changes and the per-revision fragment copies of Living Course, read by the semantic diff (ADR 0192, 0031). */
final class LivingCourseSourceHistory implements SourceHistory
{
    public function repointed(array $fragmentIds): array
    {
        $map = [];
        FragmentChange::query()
            ->whereIn('old_fragment_id', $fragmentIds)->whereIn('new_fragment_id', $fragmentIds)
            ->whereIn('kind', ['moved', 'changed'])->whereColumn('old_fragment_id', '!=', 'new_fragment_id')
            ->orderBy('id')->get(['old_fragment_id', 'new_fragment_id'])
            ->each(function (FragmentChange $change) use (&$map) {
                $map[$change->old_fragment_id] = $change->new_fragment_id;
            });

        return $map;
    }

    public function passage(string $fragmentId, array $revisionIds): ?array
    {
        $row = RevisionFragment::query()->where('fragment_id', $fragmentId)->whereIn('revision_id', $revisionIds)->first();
        if ($row === null) {
            return null;
        }
        $number = Revision::query()->whereKey($row->revision_id)->value('number');

        return ['label' => $row->label(), 'text' => (string) $row->text, 'revisionId' => $row->revision_id, 'revisionNumber' => $number !== null ? (int) $number : null];
    }
}
