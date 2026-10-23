<?php

namespace Ulams\CourseVariants\Support;

use Ulams\CourseVariants\Contracts\TopicCorrespondence;

final class NoTopicCorrespondence implements TopicCorrespondence
{
    public function pairs(int $fromCourseId, int $toCourseId): array
    {
        return [];
    }
}
