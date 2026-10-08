<?php

namespace EscolaLms\Tasks\Http\Requests\Admin;

use EscolaLms\Tasks\Dtos\AdminUpdateTaskDto;
use EscolaLms\Tasks\Http\Requests\TaskRequest;
use Illuminate\Support\Facades\Gate;

class AdminUpdateTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getTask());
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'related_type' => ['nullable', 'string', 'required_with:related_id'],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'due_date' => ['date', 'after_or_equal:now'],
        ];
    }

    public function toDto(): AdminUpdateTaskDto
    {
        return AdminUpdateTaskDto::instantiateFromRequest($this);
    }
}
