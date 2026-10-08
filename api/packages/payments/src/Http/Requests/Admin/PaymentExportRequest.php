<?php

namespace EscolaLms\Payments\Http\Requests\Admin;

use EscolaLms\Payments\Enums\ExportFormatEnum;
use EscolaLms\Payments\Models\Payment;
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
