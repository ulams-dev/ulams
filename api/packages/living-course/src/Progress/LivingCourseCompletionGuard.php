<?php

namespace Ulams\LivingCourse\Progress;

use Illuminate\Support\Facades\DB;
use Ulams\Core\Models\User;
use Ulams\LivingCourse\Support\ProposalBranch;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Services\Contracts\CourseCompletionGuardContract;

/**
 * ADR 0033: a learner who finished a course built with the Course Builder keeps `finished` when the
 * only topics they have not completed were added by a Living Course update. A topic that existed
 * before any update and is incomplete is a real gap, and then the upstream behaviour applies.
 */
final class LivingCourseCompletionGuard implements CourseCompletionGuardContract
{
    public function mayUnfinish(Course $course, User $user): bool
    {
        $branch = ProposalBranch::ofCourse((int) $course->getKey());
        if ($branch === null) {
            return true;
        }
        $active = Topic::query()->whereIn('lesson_id', $course->lessons()->select('id'))->where('active', true)->pluck('id')->all();
        $complete = DB::table('course_progress')->where('user_id', $user->getKey())->whereIn('topic_id', $active)->where('status', ProgressStatus::COMPLETE)->pluck('topic_id')->all();
        $missing = array_values(array_diff($active, $complete));
        if ($missing === []) {
            return true;
        }
        $added = $branch->entries()->whereIn('entity_type', ['topic', 'quiz_topic'])->whereIn('entity_id', $missing)->whereNotNull('added_by_proposal_id')->pluck('entity_id')->all();

        return count($added) !== count($missing);
    }
}
