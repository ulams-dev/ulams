<?php

namespace EscolaLms\CourseAccess\Services;

use EscolaLms\Auth\Models\Group;
use EscolaLms\Auth\Models\GroupUser;
use EscolaLms\Core\Models\User;
use EscolaLms\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use EscolaLms\Courses\Events\CourseAccessStarted;
use EscolaLms\Courses\Events\CourseAssigned;
use EscolaLms\Courses\Events\CourseFinished;
use EscolaLms\Courses\Events\CourseUnassigned;
use EscolaLms\CourseAccess\Models\Course;
use EscolaLms\Courses\Models\CourseGroupPivot;
use EscolaLms\Courses\Models\CourseUserPivot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CourseAccessService implements CourseAccessServiceContract
{
    public function addAccessForUsers(Course $course, array $users = []): void
    {
        if (!empty($users)) {
            $changes = $course->users()->syncWithoutDetaching($users);
            $this->dispatchEventForUsersAttachedToCourse($course, $changes['attached']);
        }
    }

    public function addAccessForGroups(Course $course, array $groups = []): void
    {
        if (!empty($groups)) {
            $course->groups()->syncWithoutDetaching($groups);
        }
    }

    public function removeAccessForUsers(Course $course, array $users = []): void
    {
        if (!empty($users)) {
            $course->users()->detach($users);
            $this->dispatchEventForUsersDetachedFromCourse($course, $users);
        }
    }

    public function removeAccessForGroups(Course $course, array $groups = []): void
    {
        if (!empty($groups)) {
            $course->groups()->detach($groups);
        }
    }

    public function setAccessForUsers(Course $course, array $users = []): void
    {
        $changes = $course->users()->sync($users);
        $this->dispatchEventForUsersAttachedToCourse($course, $changes['attached']);
        $this->dispatchEventForUsersDetachedFromCourse($course, $changes['detached']);
    }

    public function setAccessForGroups(Course $course, array $groups = []): void
    {
        $course->groups()->sync($groups);
    }

    public function getUserCourseIds(int $userId, ?bool $active = null): array
    {
        $userGroupIds = GroupUser::where('user_id', $userId)->pluck('group_id');
        $childGroupIds = $this->getChildGroups($userGroupIds);

        $courseUserIds = CourseUserPivot::query()
            ->where('user_id', $userId)
            ->when($active, fn(Builder $query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', Carbon::now())
            )
            ->pluck('course_id');

        $courseGroupIds = CourseGroupPivot::whereIn('group_id', $userGroupIds->concat($childGroupIds))->pluck('course_id');

        return $courseUserIds
            ->concat($courseGroupIds)
            ->unique()
            ->values()
            ->toArray();
    }

    private function getChildGroups(Collection $groupIds): Collection
    {
        $childGroups = Group::whereIn('parent_id', $groupIds)->pluck('id');
        if (!$childGroups->isEmpty()) {
            $childGroups->concat($this->getChildGroups($childGroups));
        }
        return $childGroups;
    }

    private function dispatchEventForUsersAttachedToCourse(Course $course, array $users = []): void
    {
        foreach ($users as $attached) {
            /** @var User $user */
            $user = is_int($attached) ? User::find($attached) : $attached;
            if ($user) {
                event(new CourseAssigned($user, $course));
                event(new CourseAccessStarted($user, $course));
            }
        }
    }

    private function dispatchEventForUsersDetachedFromCourse(Course $course, array $users = []): void
    {
        foreach ($users as $detached) {
            /** @var User $user */
            $user = is_int($detached) ? User::find($detached) : $detached;
            if ($user) {
                event(new CourseUnassigned($user, $course));
                event(new CourseFinished($user, $course));
            }
        }
    }
}
