<?php

namespace Ulams\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Tenancy\Enums\TenancyPermissionEnum;
use Ulams\Tenancy\Support\EntryPointRules;

/**
 * Creating or changing an entry point (permission `entry_points_manage`). The shape of the input is checked
 * here and again by the platform service; what only this tenant's database knows (the group or the course
 * exists) is checked by the controller with {@see \Ulams\Tenancy\Support\EntryTargets}.
 */
class EntryPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can(TenancyPermissionEnum::ENTRY_POINTS_MANAGE);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->isMethod('POST') ? EntryPointRules::create() : EntryPointRules::update();
    }
}
