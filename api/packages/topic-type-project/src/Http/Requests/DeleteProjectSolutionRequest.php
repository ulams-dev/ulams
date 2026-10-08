<?php

namespace EscolaLms\TopicTypeProject\Http\Requests;

use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteProjectSolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deleteOwn', $this->getProjectSolution());
    }

    public function rules(): array
    {
        return [];
    }

    public function getProjectSolution(): ProjectSolution
    {
        return ProjectSolution::findOrFail($this->route('id'));
    }
}
