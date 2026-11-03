<?php

namespace Ulams\LivingCourse\Support;

use Ulams\AuthoringMetrics\Contracts\SubjectCourseResolver;
use Ulams\AuthoringMetrics\Services\SubjectResolvers;
use Ulams\LivingCourse\Models\Proposal;

/**
 * Model calls that analyse sources or learner struggles into proposals (kinds `source` and `insights`) are the cost of
 * keeping the course up to date: proposal, session, course. Only loaded when the authoring metrics package is installed.
 */
class ProposalSubjectResolver implements SubjectCourseResolver
{
    public function subjectType(): string
    {
        return Proposal::SUBJECT_TYPE;
    }

    public function bucket(): string
    {
        return SubjectResolvers::MAINTENANCE;
    }

    public function subjectIds(array $sessionIds): array
    {
        return $sessionIds === [] ? [] : Proposal::query()->whereIn('session_id', $sessionIds)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
}
