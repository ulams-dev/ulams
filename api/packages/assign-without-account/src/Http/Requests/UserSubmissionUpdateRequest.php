<?php

namespace EscolaLms\AssignWithoutAccount\Http\Requests;

use EscolaLms\AssignWithoutAccount\Enums\UserSubmissionStatusEnum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserSubmissionUpdateRequest extends UserSubmissionRequest
{
    public function authorize(): bool
    {
        $userSubmission = $this->getUserSubmission();
        return Gate::allows('update', $userSubmission);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns', 'string'],
            'morphable_type' => ['required', 'string'],
            'morphable_id' => ['required', 'numeric'],
            'status' => ['string', Rule::in(UserSubmissionStatusEnum::getValues())],
        ];
    }
}
