<?php

namespace Ulams\Cart\Http\Requests\Admin;

use Ulams\Cart\Enums\ExportFormatEnum;
use Ulams\Cart\Models\Order;
use Illuminate\Validation\Rule;

class OrderExportRequest extends OrderSearchRequest
{
    public function authorize()
    {
        return $this->user('api') && $this->user('api')->can('export', Order::class);
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            'format' => ['sometimes', 'string', Rule::in(ExportFormatEnum::getValues())],
        ]);
    }
}
