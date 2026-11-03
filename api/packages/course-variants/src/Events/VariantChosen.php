<?php

namespace Ulams\CourseVariants\Events;

/** A learner is now in a variant (first choice or a switch). Ids and the source only; no learner data beyond the id. */
final class VariantChosen
{
    public function __construct(
        public readonly int $userId,
        public readonly int $defaultCourseId,
        public readonly int $courseId,
        public readonly string $source,
        public readonly ?int $previousCourseId,
    ) {
    }
}
