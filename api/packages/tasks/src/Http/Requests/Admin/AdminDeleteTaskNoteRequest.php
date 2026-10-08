<?php

namespace EscolaLms\Tasks\Http\Requests\Admin;

use EscolaLms\Tasks\Http\Requests\DeleteTaskNoteRequest;
use Illuminate\Support\Facades\Gate;

class AdminDeleteTaskNoteRequest extends DeleteTaskNoteRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getTaskNote($this->route('id')));
    }
}
