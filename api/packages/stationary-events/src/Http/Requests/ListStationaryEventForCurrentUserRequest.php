<?php

namespace EscolaLms\StationaryEvents\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListStationaryEventForCurrentUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string'],
        ];
    }
}
