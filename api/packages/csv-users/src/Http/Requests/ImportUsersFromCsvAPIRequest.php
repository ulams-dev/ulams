<?php

namespace Ulams\CsvUsers\Http\Requests;

use Ulams\CsvUsers\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ImportUsersFromCsvAPIRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', User::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'mimes:csv,txt,xlsx,xls'],
            'return_url' => ['required', 'string'],
        ];
    }
}
