<?php

namespace EscolaLms\Permissions\Http\Requests;

use EscolaLms\Permissions\Enums\PermissionsPermissionsEnum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;

class RoleListingRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::check(PermissionsPermissionsEnum::PERMISSIONS_ROLE_LIST);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string'],
            'order_by' => ['sometimes', 'string', 'in:name,id'],
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
        ];
    }
}
