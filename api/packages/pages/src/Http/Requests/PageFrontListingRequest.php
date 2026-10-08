<?php

namespace EscolaLms\Pages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PageFrontListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
