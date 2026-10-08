<?php

namespace EscolaLms\CsvUsers\Http\Requests;

use EscolaLms\CsvUsers\Models\Group;
use Illuminate\Foundation\Http\FormRequest;

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
            'return_url' => ['required', 'string'],
        ];
    }
}
