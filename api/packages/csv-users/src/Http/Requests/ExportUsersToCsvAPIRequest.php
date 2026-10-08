<?php

namespace EscolaLms\CsvUsers\Http\Requests;

use EscolaLms\Auth\Http\Requests\Admin\UsersListRequest;
use EscolaLms\CsvUsers\Enums\ExportFormatEnum;
use EscolaLms\CsvUsers\Models\User;
use Illuminate\Validation\Rule;

class ExportUsersToCsvAPIRequest extends UsersListRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('export', User::class);
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            'format' => ['sometimes', 'string', Rule::in(ExportFormatEnum::getValues())]
        ]);
    }
}
