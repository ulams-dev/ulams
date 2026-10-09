<?php

namespace Ulams\Lti\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Lti\Enums\LtiPermissionsEnum;

class LtiManageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(LtiPermissionsEnum::LTI_MANAGE, 'api');
    }

    public function rules(): array
    {
        return [];
    }
}
