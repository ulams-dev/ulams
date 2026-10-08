<?php

namespace EscolaLms\CoursesImportExport\Events;

use EscolaLms\Courses\Models\Course;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class CloneCourseEvent
{
    use Dispatchable, SerializesModels;

    private Authenticatable $user;
    private Course $course;

    /**
     * @param Authenticatable $user
     * @param Course $course
     */
    public function __construct(Authenticatable $user, Course $course)
    {
        $this->user = $user;
        $this->course = $course;
    }

    /**
     * @return Course
     */
    public function getCourse(): Course
    {
        return $this->course;
    }

    /**
     * @return Authenticatable
     */
    public function getUser(): Authenticatable
    {
        return $this->user;
    }
}
