<?php

namespace Ulams\Tasks\Events;

use Ulams\Tasks\Models\Task;
use Ulams\Tasks\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class TaskEvent
{
    use Dispatchable, SerializesModels;

    private User $user;

    private Task $task;

    public function __construct(User $user, Task $task)
    {
        $this->user = $user;
        $this->task = $task;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTask(): Task
    {
        return $this->task;
    }
}
