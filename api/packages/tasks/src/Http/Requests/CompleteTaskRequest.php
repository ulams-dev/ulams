<?php

namespace EscolaLms\Tasks\Http\Requests;

use Illuminate\Support\Facades\Gate;

class CompleteTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $task = $this->getTask();

        return Gate::allows('updateOwn', $task) && ($this->isOwner($task) || $this->isAssigned($task));
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
