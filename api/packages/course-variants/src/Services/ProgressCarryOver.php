<?php

namespace Ulams\CourseVariants\Services;

use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Services\Contracts\ProgressServiceContract;
use Ulams\CourseVariants\Contracts\TopicCorrespondence;
use Ulams\CourseVariants\Models\VariantGroup;

/**
 * Moves completion between the courses of a family when a learner changes variant (ADR 0192): a topic that is the same
 * content in both courses (the correspondence decides) and is complete in another course of the family becomes complete
 * in the new one. Written through the courses progress service, never by writing tables. Quiz attempts, scores and H5P
 * results are not copied, and the old course keeps its progress, so switching back restores it.
 */
final class ProgressCarryOver
{
    public function __construct(private readonly TopicCorrespondence $topics, private readonly ProgressServiceContract $progress)
    {
    }

    /** @return int how many topics became complete in `$toCourseId` */
    public function carry(int $userId, VariantGroup $group, int $toCourseId): int
    {
        $course = Course::query()->find($toCourseId);
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->find($userId);
        if ($course === null || $user === null) {
            return 0;
        }
        $items = [];
        foreach ($group->courseIds() as $fromCourseId) {
            if ($fromCourseId === $toCourseId) {
                continue;
            }
            $pairs = $this->topics->pairs($fromCourseId, $toCourseId);
            if ($pairs === []) {
                continue;
            }
            $done = CourseProgress::query()->where('user_id', $userId)->where('status', ProgressStatus::COMPLETE)->whereIn('topic_id', array_keys($pairs))->pluck('topic_id');
            foreach ($done as $topicId) {
                $items[$pairs[(int) $topicId]] = ['topic_id' => $pairs[(int) $topicId], 'status' => ProgressStatus::COMPLETE];
            }
        }
        if ($items === []) {
            return 0;
        }
        $before = $this->completed($userId, array_keys($items));
        $this->progress->update($course, $user, array_values($items));

        return count($this->completed($userId, array_keys($items))) - count($before);
    }

    /** @param int[] $topicIds @return int[] */
    private function completed(int $userId, array $topicIds): array
    {
        return CourseProgress::query()->where('user_id', $userId)->where('status', ProgressStatus::COMPLETE)->whereIn('topic_id', $topicIds)->pluck('topic_id')->all();
    }
}
