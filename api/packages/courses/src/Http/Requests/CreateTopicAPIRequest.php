<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;

class CreateTopicAPIRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $user = auth()->user();
        $lesson = Lesson::find($this->input('lesson_id'));
        if (!isset($lesson)) {
            return true; // controller will fire 404 error
        }
        $course = $lesson->course;
        return isset($user) ? $user->can('update', $course) : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = Topic::rules();
        $rules['lesson_id'][] = 'required';
        return $rules;
    }
}
