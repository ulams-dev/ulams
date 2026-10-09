<?php

namespace Ulams\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Core\Enums\UserRole;

class CspReportListRequest extends FormRequest
{
    /** Admins only: the reports show which pages load what from where. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(UserRole::ADMIN);
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
