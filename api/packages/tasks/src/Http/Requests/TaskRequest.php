<?php

namespace EscolaLms\Tasks\Http\Requests;

use EscolaLms\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

abstract class TaskRequest extends FormRequest
{
    public function isOwner(?Task $task = null): bool
    {
        $task = $task ?? $this->getTask();

        if (auth()->id() && $task->user_id === auth()->id() && $task->created_by_id === auth()->id()) {
            return true;
        }

        return false;
    }

    public function isCreator(?Task $task = null): bool
    {
        $task = $task ?? $this->getTask();

        if (auth()->id() && $task->created_by_id === auth()->id()) {
            return true;
        }

        return false;
    }

    public function isAssigned(?Task $task = null): bool
    {
        $task = $task ?? $this->getTask();

        if (auth()->id() && $task->user_id === auth()->id()) {
            return true;
        }

        return false;
    }

    public function getTask(?int $id = null): Task
    {
        return Task::findOrFail($id ?? $this->route('id'));
    }

    public function rules(): array
    {
        return [];
    }
}
