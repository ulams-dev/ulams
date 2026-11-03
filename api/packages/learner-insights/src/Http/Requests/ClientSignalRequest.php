<?php

namespace Ulams\LearnerInsights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ulams\LearnerInsights\Signals\SignalType;

class ClientSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(SignalType::CLIENT_TYPES)],
            'topicId' => ['required', 'integer', 'min:1'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'media' => ['nullable', 'string', Rule::in(['video', 'audio'])],
            'ref' => ['required', 'string', 'uuid'],
        ];
    }
}
