<?php

namespace EscolaLms\Reports\Stats\Course;

class FinishedCourse extends CourseUsersAndGroupsStat
{
    public function calculate()
    {
        $users = $this->course->users()->withPivot('updated_at', 'finished');
        return array_merge($users
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'finished_at' => $user->pivot->finished ? $user->pivot->updated_at : null,
                'finished' => $user->pivot->finished,
            ])
            ->values()
            ->toArray(), $this->getGroupUsers()->values()->toArray());
    }
}
