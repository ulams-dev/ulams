<?php

namespace Ulams\LiaScript\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\LiaScript\Enums\LiaScriptPermissionsEnum;

/**
 * Authorisation for every LiaScript endpoint (permission `liascript_manage`, seeded for admins and
 * tutors); the controller validates the input of each action.
 */
class LiaScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(LiaScriptPermissionsEnum::LIASCRIPT_MANAGE, 'api');
    }

    public function rules(): array
    {
        return [];
    }
}
