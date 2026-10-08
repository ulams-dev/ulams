<?php

namespace EscolaLms\Consultations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSignedScreenUrlsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'consultation_id' => ['required', 'integer'],
            'user_termin_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
            'executed_at' => ['required'],
            'files' => ['array', 'min:1'],
            'files.*.filename' => ['required', 'string'],
        ];
    }
}
