<?php

namespace EscolaLms\Tasks\Http\Requests\Admin;

use EscolaLms\Tasks\Http\Requests\TaskRequest;
use Illuminate\Support\Facades\Gate;

class AdminDeleteTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getTask());
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
