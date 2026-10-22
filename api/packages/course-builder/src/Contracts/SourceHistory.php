<?php

namespace Ulams\CourseBuilder\Contracts;

/**
 * What the source revisions of a course know about its citations, for the semantic diff (ADR 0192, CV-2). Living Course
 * keeps every revision of a source with its own copy of each fragment and the fragment-level changes between revisions
 * (ADR 0030, 0031); the builder only asks. The default knows nothing: a citation that changed is plain added or removed,
 * and its passage is the one stored for the fragment.
 */
interface SourceHistory
{
    /**
     * Fragments that a citation may have moved from, as the fragment they moved to in a newer revision (the `moved` and
     * `changed` matches of the fragment diff). One hop, by fragment id; only pairs where both ids are in `$fragmentIds`.
     *
     * @param string[] $fragmentIds
     *
     * @return array<string,string> old fragment id => new fragment id
     */
    public function repointed(array $fragmentIds): array;

    /**
     * A fragment as it was in one of the given source revisions.
     *
     * @param string[] $revisionIds
     *
     * @return array{label:string,text:string,revisionId:string,revisionNumber:?int}|null
     */
    public function passage(string $fragmentId, array $revisionIds): ?array;
}
