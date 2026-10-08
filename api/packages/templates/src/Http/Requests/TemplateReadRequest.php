<?php

namespace EscolaLms\Templates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use EscolaLms\Templates\Models\Template;
use Illuminate\Support\Facades\Gate;

class TemplateReadRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::allows('read', Template::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }
}
