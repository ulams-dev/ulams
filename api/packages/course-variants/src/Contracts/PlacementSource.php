<?php

namespace Ulams\CourseVariants\Contracts;

/**
 * Where the placement check takes its questions from: the cited quiz questions of the family's courses, tagged with the
 * level they belong to. The Course Builder implements it from the applied blueprints; nothing is generated for it.
 */
interface PlacementSource
{
    /**
     * @param array<int,array{courseId:int,level:string}> $courses the family's courses with their level (the default course included)
     *
     * @return array<int,array{level:string,courseId:int,elementId:string,question:array}> `question` has type, stem, options (id, text, correct), explanation, citations, objectiveIds
     */
    public function questions(array $courses): array;
}
