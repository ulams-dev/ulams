<?php

namespace Ulams\Consultations\Http\Requests;

use Ulams\Consultations\Enum\ConsultationsPermissionsEnum;
use Ulams\Consultations\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListAPIConsultationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['string'],
            'status' => ['array'],
            'status.*' => ['string'],
            'order_by' => ['sometimes', 'string', 'in:id,name,status,duration,active_from,active_to,created_at'],
        ];
    }
}
