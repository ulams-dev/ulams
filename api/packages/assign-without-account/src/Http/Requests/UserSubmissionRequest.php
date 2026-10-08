<?php

namespace Ulams\AssignWithoutAccount\Http\Requests;

use Ulams\AssignWithoutAccount\Models\UserSubmission;
use Illuminate\Foundation\Http\FormRequest;

abstract class UserSubmissionRequest extends FormRequest
{
    public function getUserSubmission(): UserSubmission
    {
        return UserSubmission::findOrFail($this->route('id'));
    }
}
