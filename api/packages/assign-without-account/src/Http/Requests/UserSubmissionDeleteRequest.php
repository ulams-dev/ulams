<?php

namespace EscolaLms\AssignWithoutAccount\Http\Requests;

use Illuminate\Support\Facades\Gate;

class UserSubmissionDeleteRequest extends UserSubmissionRequest
{
    public function authorize(): bool
    {
        $userSubmission = $this->getUserSubmission();
        return Gate::allows('delete', $userSubmission);
    }

    public function rules(): array
    {
        return [];
    }
}
