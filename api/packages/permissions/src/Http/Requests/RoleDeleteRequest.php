<?php

namespace EscolaLms\Permissions\Http\Requests;

use EscolaLms\Permissions\Enums\PermissionsPermissionsEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RoleDeleteRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::check(PermissionsPermissionsEnum::PERMISSIONS_ROLE_DELETE);
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
