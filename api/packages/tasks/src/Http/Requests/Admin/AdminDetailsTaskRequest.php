<?php

namespace Ulams\Tasks\Http\Requests\Admin;

use Ulams\Tasks\Http\Requests\TaskRequest;
use Illuminate\Support\Facades\Gate;

class AdminDetailsTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $task = $this->getTask();

        return Gate::allows('find', $task);
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
