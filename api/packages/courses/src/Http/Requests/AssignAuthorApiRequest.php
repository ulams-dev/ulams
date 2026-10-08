<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Core\Enums\UserRole;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\User;
use Ulams\Courses\Repositories\Contracts\CourseRepositoryContract;
use Illuminate\Foundation\Http\FormRequest;

class AssignAuthorApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        return isset($user) ? $user->can('update', $this->getCourse()) : false;
    }

    public function rules(): array
    {
        return [];
    }

    public function getCourse(): ?Course
    {
        return Course::find($this->route('course'));
    }

    public function getTutor(): ?User
    {
        return User::find($this->route('id'));
    }
}
