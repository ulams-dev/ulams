<?php

namespace EscolaLms\Recommender\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SatisfactionSaveRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'term_analytic_id' => ['required'],
            'mean_predicted_rating' => ['required', 'numeric'],
            'satisfaction_models' => ['nullable', 'array'],
        ];
    }
}
