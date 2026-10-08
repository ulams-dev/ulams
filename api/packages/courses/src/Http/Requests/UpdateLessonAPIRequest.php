<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Rules\ValidParentLesson;
use Ulams\ModelFields\Facades\ModelFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonAPIRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $lesson = Lesson::find($this->route('lesson'));

        return isset($user) && $user->can('update', $lesson);
    }

    public function rules(): array
    {
        return array_merge(Lesson::$rules, [
            'parent_lesson_id' => ['nullable', new ValidParentLesson($this->get('course_id'))],
        ], ModelFields::getFieldsMetadataRules(Lesson::class));
    }
}
