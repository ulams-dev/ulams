<?php

namespace Ulams\Interactive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Interactive\Enums\InteractivePermissionsEnum;

/**
 * Authorisation for every admin library endpoint (permission `interactive_manage`, seeded for
 * admins and tutors); the controller validates the input of each action.
 */
class InteractiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(InteractivePermissionsEnum::INTERACTIVE_MANAGE, 'api');
    }

    public function rules(): array
    {
        return [];
    }
}
