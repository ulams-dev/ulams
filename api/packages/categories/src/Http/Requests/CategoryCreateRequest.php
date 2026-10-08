<?php

namespace EscolaLms\Categories\Http\Requests;

use EscolaLms\Categories\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class CategoryCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return isset($user) ? $user->can('create', Category::class) : false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'bool'],
            'icon' => ['nullable', 'file', 'image'],
            'icon_class' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
