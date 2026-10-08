<?php

namespace EscolaLms\Tasks\Events;

use EscolaLms\Tasks\Models\TaskNote;
use EscolaLms\Tasks\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class TaskNoteEvent
{
    use Dispatchable, SerializesModels;

    private User $user;

    private TaskNote $taskNote;

    public function __construct(User $user, TaskNote $taskNote)
    {
        $this->user = $user;
        $this->taskNote = $taskNote;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTaskNote(): TaskNote
    {
        return $this->taskNote;
    }
}
