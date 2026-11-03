<?php

namespace Ulams\AuthoringMetrics\Contracts;

/**
 * Tells the authoring metric which AI calls belong to the course of a builder session. Each package that makes
 * model calls about a course resolves its own `ai_calls.subject_type`, so `ai` never learns about courses.
 * Subjects that are not authoring (tutor answers, learner remediation) have no resolver and are not counted.
 */
interface SubjectCourseResolver
{
    /** The `ai_calls.subject_type` this resolver covers. */
    public function subjectType(): string;

    /** `build` or `maintenance`. */
    public function bucket(): string;

    /**
     * @param array<int,string> $sessionIds Course Builder sessions of the course
     * @return array<int,string> the `subject_id`s of this type that belong to those sessions
     */
    public function subjectIds(array $sessionIds): array;
}
