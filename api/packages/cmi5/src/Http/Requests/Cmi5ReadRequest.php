<?php

namespace EscolaLms\Cmi5\Http\Requests;

use EscolaLms\Cmi5\Models\Cmi5;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class Cmi5ReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', Cmi5::class);
    }

    public function rules(): array
    {
        return [
            'au_id' => ['integer', 'required', 'exists:cmi5_aus,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'au_id' => $this->route('auId'),
            'course_id' => $this->get('course_id'),
            'topic_id' => $this->get('topic_id'),
        ]);
    }
}
