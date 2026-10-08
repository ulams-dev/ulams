<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Rules\ValidEnum;
use Illuminate\Foundation\Http\FormRequest;

class CourseProgressAPIRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'progress' => 'array',
            'progress.*.topic_id' => ['numeric', 'exists:topics,id'],
            'progress.*.status' => ['numeric', new ValidEnum(ProgressStatus::class)]
        ];
    }
}
