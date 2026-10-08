<?php

namespace EscolaLms\AssignWithoutAccount\Http\Requests;

use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UserSubmissionCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', UserSubmission::class);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns', 'string'],
            'morphable_type' => ['required', 'string'],
            'morphable_id' => ['required', 'numeric'],
        ];
    }
}
