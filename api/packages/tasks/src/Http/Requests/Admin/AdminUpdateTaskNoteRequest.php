<?php

namespace Ulams\Tasks\Http\Requests\Admin;

use Ulams\Tasks\Http\Requests\UpdateTaskNoteRequest;
use Illuminate\Support\Facades\Gate;

class AdminUpdateTaskNoteRequest extends UpdateTaskNoteRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getTaskNote($this->route('id')));
    }
}
