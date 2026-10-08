<?php

namespace EscolaLms\AssignWithoutAccount\Http\Requests;

use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UserSubmissionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', UserSubmission::class);
    }

    public function rules(): array
    {
        return [];
    }
}
