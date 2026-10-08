<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Rules\ValidAuthor;
use Illuminate\Foundation\Http\FormRequest;

class CreateCourseAPIRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        return isset($user) ? $user->can('create', Course::class) : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = array_merge(Course::rules(), [
            'title' => ['required', 'string', "min:3"],
        ]);
        $rules['authors.*'][] = new ValidAuthor();
        return $rules;
    }
}
