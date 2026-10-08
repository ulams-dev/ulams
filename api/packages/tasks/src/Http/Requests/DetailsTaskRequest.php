<?php

namespace EscolaLms\Tasks\Http\Requests;

use Illuminate\Support\Facades\Gate;

class DetailsTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $task = $this->getTask();

        return Gate::allows('findOwn', $task) && ($this->isOwner($task) || $this->isAssigned($task));
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
