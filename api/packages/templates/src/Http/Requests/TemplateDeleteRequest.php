<?php

namespace EscolaLms\Templates\Http\Requests;

use EscolaLms\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TemplateDeleteRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('delete', Template::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
}
