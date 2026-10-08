<?php

namespace Ulams\Recommender\Http\Requests;

use Ulams\Recommender\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CourseRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('course', $this->getCourse());
    }

    public function rules(): array
    {
        return [];
    }

    private function getCourse(): Course
    {
        return Course::findOrFail($this->route('courseId'));
    }
}
