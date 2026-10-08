<?php

namespace EscolaLms\Recommender\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AggregatedFrameListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interval' => ['required', 'integer', 'in:15,30,60,300'],
        ];
    }
}
