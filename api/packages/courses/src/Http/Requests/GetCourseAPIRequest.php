<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GetCourseAPIRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $user = auth()->user();
        $course = $this->getCourse();
        if (is_null($course)) {
            return true; // controller will fire 404 error
        }
        return Gate::check('view', $course);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    public function getCourse(): ?Course
    {
        return Course::find($this->route('course'));
    }

    public function getTopic(): ?Topic
    {
        return Topic::find($this->route('topic_id'));
    }
}
