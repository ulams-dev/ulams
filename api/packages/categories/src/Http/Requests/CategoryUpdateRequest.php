<?php

namespace EscolaLms\Categories\Http\Requests;

use EscolaLms\Categories\Enums\ConstantEnum;
use EscolaLms\Categories\Models\Category;
use EscolaLms\Files\Rules\FileOrStringRule;
use Illuminate\Foundation\Http\FormRequest;

class CategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $category = Category::find($this->getCategoryId());

        return isset($user) ? $user->can('update', $category) : false;
    }

    public function rules(): array
    {
        $prefixPath = ConstantEnum::DIRECTORY . '/' . $this->getCategoryId();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'bool'],
            'icon' => [new FileOrStringRule(['image'], $prefixPath)],
            'icon_class' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'order' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function getCategoryId()
    {
        return $this->route('category');
    }
}
