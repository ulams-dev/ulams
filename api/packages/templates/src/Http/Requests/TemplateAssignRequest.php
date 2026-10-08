<?php

namespace EscolaLms\Templates\Http\Requests;

use EscolaLms\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TemplateAssignRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('update', Template::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'assignable_id' => ['required', 'int', 'nullable'],
        ];
    }

    public function getTemplate(): ?Template
    {
        return Template::find($this->route('id'));
    }
}
