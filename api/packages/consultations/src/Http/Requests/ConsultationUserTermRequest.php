<?php

namespace EscolaLms\Consultations\Http\Requests;

use EscolaLms\Consultations\Rules\UserTermExist;
use Illuminate\Foundation\Http\FormRequest;

class ConsultationUserTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        return isset($user);
    }

    public function rules(): array
    {
        return [
            'term' => ['required', 'date', new UserTermExist(request('consultationTermId'))],
            'finished_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
