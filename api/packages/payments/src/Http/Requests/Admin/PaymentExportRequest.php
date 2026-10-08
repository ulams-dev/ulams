<?php

namespace Ulams\Payments\Http\Requests\Admin;

use Ulams\Payments\Enums\ExportFormatEnum;
use Ulams\Payments\Models\Payment;
use Illuminate\Validation\Rule;

class PaymentExportRequest extends PaymentsSearchAdminRequest
{
    public function authorize(): bool
    {
        return $this->user('api') && $this->user('api')->can('export', Payment::class);
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            'format' => ['sometimes', 'string', Rule::in(ExportFormatEnum::getValues())],
        ]);
    }
}
