<?php

namespace EscolaLms\Tasks\Http\Requests;

use EscolaLms\Tasks\Dtos\UpdateTaskDto;
use Illuminate\Support\Facades\Gate;

class UpdateTaskRequest extends CreateTaskRequest
{
    public function authorize(): bool
    {
        $task = $this->getTask();

        return Gate::allows('updateOwn', $task) && $this->isOwner($task);
    }

    public function toDto(): UpdateTaskDto
    {
        return UpdateTaskDto::instantiateFromRequest($this);
    }
}
