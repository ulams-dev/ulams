<?php

namespace Ulams\Courses\Services\Contracts;

use Ulams\Courses\Models\Lesson;
use Illuminate\Database\Eloquent\Model;

interface LessonServiceContract
{
    public function cloneLesson(Lesson $lesson): Model;
}
