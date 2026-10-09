<?php

namespace Ulams\Courses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCourseAPIRequest extends FormRequest
{
    /**
     * The public catalogue: anyone may list it, logged in or not. What a caller sees is decided
     * in CourseAPIController::index (users who cannot create courses only get published,
     * findable ones), not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['array', 'prohibited_unless:category_id,null'],
            'categories.*' => ['integer'],
            'category_id' => ['integer'],
            'group_id' => ['integer'],
            'no_expired' => ['boolean'],
            'order_by' => ['string', 'in:id,title,created_at,status'],
        ];
    }
}
