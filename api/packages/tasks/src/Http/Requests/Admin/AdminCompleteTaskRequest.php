<?php

namespace EscolaLms\Tasks\Http\Requests\Admin;

use EscolaLms\Tasks\Http\Requests\TaskRequest;
use EscolaLms\Tasks\Models\Task;
use Illuminate\Support\Facades\Gate;

class AdminCompleteTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getTask());
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
