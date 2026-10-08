<?php

namespace EscolaLms\Categories\Http\Requests;

use EscolaLms\Categories\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class CategoryReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        return isset($user) ? $user->can('read', Category::class) : false;
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
