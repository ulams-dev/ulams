<?php

namespace EscolaLms\Templates\Http\Requests;

use EscolaLms\Templates\Enums\TemplatesPermissionsEnum;
use EscolaLms\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

class EventTriggerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return !is_null($user) && $user->can(TemplatesPermissionsEnum::EVENTS_TRIGGER);
    }

    public function rules(): array
    {
        return [
            'users' => ['required', 'array'],
            'users.*' => ['integer', 'exists:users,id'],
            'course' => ['nullable', 'exists:courses,id'],
            'product' => ['nullable', 'exists:products,id'],
        ];
    }

    public function getTemplate(): ?Template
    {
        return Template::findOrFail($this->route('id'));
    }
}
