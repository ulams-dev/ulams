<?php

namespace EscolaLms\Tags\Http\Request;

use EscolaLms\Tags\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

class TagInsertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create', Tag::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'tags' => 'required|array',
        ];
    }
}
