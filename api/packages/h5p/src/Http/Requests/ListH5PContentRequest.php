<?php

namespace Ulams\H5P\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Ulams\H5P\Dtos\H5PContentCriteriaDto;
use Ulams\H5P\Models\H5PContent;
use Ulams\H5P\Services\H5PContentService;

/**
 * @OA\Schema(
 *      schema="H5PContentListRequest",
 *      @OA\Property(property="title", type="string"),
 *      @OA\Property(property="main_library", type="string"),
 *      @OA\Property(property="author_id", type="integer"),
 *      @OA\Property(property="per_page", type="integer"),
 *      @OA\Property(property="order_by", type="string"),
 *      @OA\Property(property="order", type="string"),
 * )
 */
class ListH5PContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', H5PContent::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string'],
            'main_library' => ['sometimes', 'nullable', 'string'],
            'author_id' => ['sometimes', 'nullable'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:0'],
            'order_by' => ['sometimes', 'nullable', 'string', Rule::in(H5PContentService::ORDER_COLUMNS)],
            'order' => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }

    public function getCriteria(): H5PContentCriteriaDto
    {
        return H5PContentCriteriaDto::instantiateFromRequest($this);
    }

    public function getPerPage(): int
    {
        return (int) $this->input('per_page', config('paginate.default.limit', 15));
    }

    public function getOrderBy(): string
    {
        return $this->input('order_by') ?: 'id';
    }

    public function getOrder(): string
    {
        return $this->input('order') ?: 'desc';
    }
}
