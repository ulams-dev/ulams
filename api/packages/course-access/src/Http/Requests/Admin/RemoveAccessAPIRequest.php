<?php

namespace Ulams\CourseAccess\Http\Requests\Admin;

use Ulams\CourseAccess\Http\Requests\Admin\Abstracts\CourseAccessAPIRequest;
use Illuminate\Validation\Rule;

class RemoveAccessAPIRequest extends CourseAccessAPIRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'groups' => ['sometimes', 'array'],
            'groups.*' => ['integer', Rule::exists('groups', 'id')],
            'users' => ['sometimes', 'array'],
            'users.*' => ['integer', Rule::exists('users', 'id')],
        ]);
    }
}
