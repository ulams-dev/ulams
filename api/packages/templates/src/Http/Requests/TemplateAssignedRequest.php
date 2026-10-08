<?php

namespace EscolaLms\Templates\Http\Requests;

use EscolaLms\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TemplateAssignedRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('list', Template::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'assignable_class' => ['required', 'string'],
            'assignable_id' => ['required', 'int', 'nullable'],
        ];
    }
}
