<?php

namespace EscolaLms\Categories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'order_by' => ['sometimes', Rule::in(['id', 'created_at', 'name', 'slug', 'status', 'is_active', 'order'])],
            'order' => ['sometimes', Rule::in(['ASC', 'DESC'])],
            'page' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer'],
        ];
    }
}
