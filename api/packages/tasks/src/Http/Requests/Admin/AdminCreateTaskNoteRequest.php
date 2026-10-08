<?php

namespace EscolaLms\Tasks\Http\Requests\Admin;

use EscolaLms\Tasks\Http\Requests\CreateTaskNoteRequest;
use EscolaLms\Tasks\Models\TaskNote;
use Illuminate\Support\Facades\Gate;

class AdminCreateTaskNoteRequest extends CreateTaskNoteRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', TaskNote::class);
    }
}
