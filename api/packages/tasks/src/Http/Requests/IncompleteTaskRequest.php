<?php

namespace Ulams\Tasks\Http\Requests;

use Ulams\Tasks\Models\Task;
use Illuminate\Support\Facades\Gate;

class IncompleteTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $task = $this->getTask();

        return Gate::allows('updateOwn', $task) && $this->isOwner($task);
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
