<?php

namespace Ulams\CourseVariants\Contracts;

/**
 * Whether the variant a learner is in looks too easy or too hard for them, by transparent rules (Learner Insights
 * implements it). It only ever produces a hint the learner may dismiss; nothing switches variant by itself.
 */
interface FitSignals
{
    /**
     * @return array{direction:'easier'|'harder',reasons:array<int,array{rule:string,messageKey:string,params:array<string,scalar>,message?:string}>}|null
     */
    public function assess(int $userId, int $courseId): ?array;
}
