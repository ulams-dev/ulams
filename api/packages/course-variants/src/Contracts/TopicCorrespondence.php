<?php

namespace Ulams\CourseVariants\Contracts;

/**
 * Which topics of two variant courses are the same content, so a learner's completion can move with them (ADR 0192).
 * The Course Builder implements it from its entity map (same element id and the same content, parent and fingerprint);
 * a family made by hand has none and carries nothing over.
 */
interface TopicCorrespondence
{
    /**
     * @return array<int,int> topic id in `$fromCourseId` => topic id in `$toCourseId`, only for identical content
     */
    public function pairs(int $fromCourseId, int $toCourseId): array;
}
