<?php

namespace Ulams\LivingCourse\Progress;

use Ulams\CourseBuilder\Contracts\FragmentArchive;
use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;

/** Fragments that left the live table stay readable from the revision that had them last (ADR 0030). */
final class LivingCourseFragmentArchive implements FragmentArchive
{
    public function find(string $fragmentId): ?array
    {
        $row = RevisionFragment::query()
            ->join('living_course_revisions', 'living_course_revisions.id', '=', 'living_course_revision_fragments.revision_id')
            ->where('living_course_revision_fragments.fragment_id', $fragmentId)
            ->orderByDesc('living_course_revisions.number')
            ->first(['living_course_revision_fragments.*', 'living_course_revisions.number as revision_number', 'living_course_revisions.source_id as revision_source_id']);
        $source = $row !== null ? Source::query()->find($row->revision_source_id) : null;
        if ($row === null || $source === null) {
            return null;
        }

        return [
            'id' => $row->fragment_id,
            'label' => $row->label(),
            'section' => $row->section,
            'headingPath' => $row->heading_path,
            'text' => $row->text,
            'pageStart' => $row->page_start,
            'pageEnd' => $row->page_end,
            'source' => ['id' => $source->id, 'name' => $source->original_name],
            'sessionId' => $source->session_id,
            'revision' => (int) $row->revision_number,
        ];
    }

    public function knownIds(string $sessionId): array
    {
        return RevisionFragment::query()
            ->whereIn('revision_id', Revision::query()->whereIn('source_id', Source::query()->where('session_id', $sessionId)->select('id'))->select('id'))
            ->distinct()->pluck('fragment_id')->all();
    }
}
