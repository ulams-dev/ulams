<?php

namespace EscolaLms\Categories\Http\Requests;

use EscolaLms\Categories\Dtos\CategorySortDto;
use EscolaLms\Categories\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminCategorySortRequest",
 *      @OA\Property(
 *          property="orders",
 *          description="orders",
 *          type="array",
 *           @OA\Items(
 *              ref="#/components/schemas/AdminSortCategories"
 *          )
 *      )
 * )
 *
 *
 * @OA\Schema(
 *      schema="AdminSortCategories",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="order",
 *          description="order",
 *          type="integer"
 *      )
 * )
 *
 */
class CategorySortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Category::class);
    }

    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'exists:categories,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toDto(): CategorySortDto
    {
        return CategorySortDto::instantiateFromRequest($this);
    }
}
