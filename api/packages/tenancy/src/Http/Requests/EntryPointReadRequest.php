<?php

namespace Ulams\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Tenancy\Enums\TenancyPermissionEnum;

/** Reading, disabling or deleting entry points: no input, only the permission `entry_points_manage`. */
class EntryPointReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can(TenancyPermissionEnum::ENTRY_POINTS_MANAGE);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
