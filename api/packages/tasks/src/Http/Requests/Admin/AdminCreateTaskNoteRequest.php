<?php

namespace Ulams\Tasks\Http\Requests\Admin;

use Ulams\Tasks\Http\Requests\CreateTaskNoteRequest;
use Ulams\Tasks\Models\TaskNote;
use Illuminate\Support\Facades\Gate;

class AdminCreateTaskNoteRequest extends CreateTaskNoteRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', TaskNote::class);
    }
}
