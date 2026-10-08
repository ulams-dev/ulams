<?php

namespace EscolaLms\Reports\Stats\Course;

class PeopleStarted extends CourseUsersAndGroupsStat
{
    public function calculate(): int
    {
        return $this->course->users()->wherePivot('finished', false)->count()
            + $this->getGroupUsers()->countBy(fn ($progress) => $progress['finished'])->get(0);
    }
}
