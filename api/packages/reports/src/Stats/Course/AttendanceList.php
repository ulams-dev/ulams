<?php

namespace Ulams\Reports\Stats\Course;

use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\CourseUserAttendance;
use Ulams\Courses\Models\Topic;

class AttendanceList extends AbstractCourseStat
{
    public function calculate(): array
    {
        $courseProgressTable = (new CourseProgress())->getTable();
        $topicTable = (new Topic())->getTable();
        $userTable = (new User())->getTable();
        $userAttendanceTable = (new CourseUserAttendance())->getTable();
        $topicsIds = $this->course->topics()->pluck($topicTable . '.id');

        return CourseProgress::query()
            ->selectRaw('cast(' . $userAttendanceTable . '.attendance_date as date) AS date, cast(' . $userAttendanceTable . '.attendance_date as time) AS time, ' . $userTable . '.email, ' . $userTable . '.first_name, ' . $userTable . '.last_name, ' . 'user_id, ' . $userAttendanceTable . '.attempt,' . $userAttendanceTable . '.seconds')
            ->join($userTable, $courseProgressTable . '.user_id', '=', $userTable . '.id')
            ->join($userAttendanceTable, $courseProgressTable . '.id', '=', $userAttendanceTable . '.course_progress_id')
            ->whereIn('topic_id', $topicsIds)
            ->orderBy($userAttendanceTable . '.attendance_date')
            ->get()
            ->groupBy(['user_id'])
            ->map(fn($attempts, $userId) => [
                'id' => $userId,
                'email' => $attempts[0]->email,
                'name' => $attempts[0]->first_name . ' ' . $attempts[0]->last_name,
                'attempts' => collect($attempts)->groupBy(['attempt'])->map(fn($dates, $attempt) => [
                    'attempt' => $attempt,
                    'dates' => collect($dates)->groupBy(['date'])->map(fn($times, $date) => [
                        'date' => $date,
                        'seconds_total' => collect($times)->max('seconds') - collect($times)->min('seconds'),
                    ]),
                ])->values(),
            ])
            ->values()
            ->toArray();
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(Course::class) && class_exists(CourseUserAttendance::class);
    }
}
