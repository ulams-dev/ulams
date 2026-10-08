<?php

namespace EscolaLms\Tasks\Http\Requests;

use Illuminate\Support\Facades\Gate;

class DeleteTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $task = $this->getTask();

        return Gate::allows('deleteOwn', $task) && $this->isOwner($task);
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
