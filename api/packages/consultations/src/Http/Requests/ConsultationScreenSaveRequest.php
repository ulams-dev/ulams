<?php

namespace EscolaLms\Consultations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationScreenSaveRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'consultation_id' => ['required', 'integer'],
            'user_termin_id' => ['required', 'integer'],
            'user_email' => ['required_without:user_id', 'email'],
            'user_id' => ['required_without:user_email', 'integer'],
            'executed_at' => ['required'],
            'files' => ['array', 'min:1'],
            'files.*.file' => ['required'],
            'files.*.timestamp' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }
}
