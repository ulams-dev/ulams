<?php

namespace EscolaLms\Permissions\Http\Requests;

use EscolaLms\Permissions\Enums\PermissionsPermissionsEnum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;

class RoleReadRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::check(PermissionsPermissionsEnum::PERMISSIONS_ROLE_READ);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }
}
