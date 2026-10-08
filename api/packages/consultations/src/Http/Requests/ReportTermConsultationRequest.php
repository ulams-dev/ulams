<?php

namespace EscolaLms\Consultations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportTermConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        return isset($user);
    }

    public function rules(): array
    {
        return [
            'term' => ['required', 'date', 'after_or_equal:now']
        ];
    }
}
