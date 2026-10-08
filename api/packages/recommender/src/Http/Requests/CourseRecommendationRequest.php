<?php

namespace EscolaLms\Recommender\Http\Requests;

use EscolaLms\Recommender\Models\Course;
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
