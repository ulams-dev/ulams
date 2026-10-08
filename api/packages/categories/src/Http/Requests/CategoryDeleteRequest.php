<?php

namespace EscolaLms\Categories\Http\Requests;

use EscolaLms\Categories\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class CategoryDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $category = Category::find($this->route('category'));
        return isset($user) ? $user->can('delete', $category) : false;
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
