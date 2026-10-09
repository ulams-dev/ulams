<?php

namespace Ulams\Tags\Http\Request;

use Ulams\Tags\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

class TagRemoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()?->can('delete', Tag::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'tags' => 'required|array',
        ];
    }
}
