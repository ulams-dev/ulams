<?php

namespace Ulams\Reports\Imports\Stats\Course\Sheets;

use Ulams\Auth\Models\User;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\CourseUserAttendance;
use Ulams\Courses\Models\Topic;

class FinishedTopicsAttemptsSheet extends FinishedTopicsSheet
{
    protected function processRow(User $user, Topic $topic, $value): CourseProgress
    {
        $courseProgress = parent::processRow($user, $topic, $value);

        if ((int) $value === 1) {
            CourseUserAttendance::query()->updateOrCreate([
                'course_progress_id' => $courseProgress->getKey(),
                'attempt' => 1,
                'seconds' => 0,
            ], [
                'attendance_date' => now(),
            ]);
        }

        CourseUserAttendance::query()->updateOrCreate([
            'course_progress_id' => $courseProgress->getKey(),
            'attempt' => $value,
        ], [
            'seconds' => $courseProgress->seconds,
            'attendance_date' => now(),
        ]);

        return $courseProgress;
    }

    protected function prepareUpdateData($value, ?CourseProgress $courseProgress = null): array
    {
        return [
            'attempts' => $value,
        ];
    }
}
