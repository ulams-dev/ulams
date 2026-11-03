<?php

namespace Ulams\CourseVariants\Tests\Support;

use Ulams\CourseVariants\Contracts\TopicCorrespondence;

/** Topic correspondence from a fixed table: "from:to" => [from topic id => to topic id]. */
final class FakeCorrespondence implements TopicCorrespondence
{
    /** @param array<string,array<int,int>> $table */
    public function __construct(private readonly array $table)
    {
    }

    public function pairs(int $fromCourseId, int $toCourseId): array
    {
        return $this->table["{$fromCourseId}:{$toCourseId}"] ?? [];
    }
}
