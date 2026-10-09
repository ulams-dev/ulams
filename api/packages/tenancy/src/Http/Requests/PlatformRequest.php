<?php

namespace Ulams\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Auth\Enums\AuthPermissionsEnum;

/** Base of the platform API requests: only a platform administrator (`platform_admin`) may call it. */
abstract class PlatformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can(AuthPermissionsEnum::PLATFORM_ADMIN);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }
}
