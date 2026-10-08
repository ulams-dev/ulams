<?php

namespace EscolaLms\Reports\Stats\Course;

class PeopleFinished extends CourseUsersAndGroupsStat
{
    public function calculate(): int
    {
        return $this->course->users()->wherePivot('finished', '=', true)->count()
            + $this->getGroupUsers()->countBy(fn ($progress) => $progress['finished'])->get(1);
    }
}
