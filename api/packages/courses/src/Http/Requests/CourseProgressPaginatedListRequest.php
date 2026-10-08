<?php

namespace Ulams\Courses\Http\Requests;

use BenSampo\Enum\Rules\Enum;
use Ulams\Courses\Enum\ProgressFilterEnum;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Rules\ValidEnum;
use Illuminate\Foundation\Http\FormRequest;

class CourseProgressPaginatedListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'order_by' => ['string', 'in:title,obtained'],
            'order' => ['string', 'in:asc,desc'],
            'filter' => [new Enum(ProgressFilterEnum::class)],
        ];
    }
}
