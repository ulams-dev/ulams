<?php

namespace Ulams\CsvUsers\Http\Requests;

use Ulams\CsvUsers\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Ulams\Auth\Rules\AllowedReturnUrl;

class ImportUserGroupFromCsvAPIRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', Group::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'mimes:csv,txt,xlsx,xls'],
            'return_url' => ['required', 'string', 'max:2048', new AllowedReturnUrl()],
        ];
    }
}
