<?php

namespace Ulams\Courses\Events;

use Ulams\Core\Models\User;
use Ulams\Courses\Models\Lesson;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LessonFinished
{
    use Dispatchable, SerializesModels;

    private User $user;
    private Lesson $lesson;

    public function __construct(User $user, Lesson $lesson)
    {
        $this->user = $user;
        $this->lesson = $lesson;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLesson(): Lesson
    {
        return $this->lesson;
    }
}
