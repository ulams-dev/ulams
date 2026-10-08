<?php

namespace Ulams\CsvUsers\Http\Requests;

use Ulams\Auth\Http\Requests\Admin\UsersListRequest;
use Ulams\CsvUsers\Enums\ExportFormatEnum;
use Ulams\CsvUsers\Models\User;
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
