<?php

namespace EscolaLms\TopicTypeProject\Http\Requests\Admin;

use EscolaLms\TopicTypeProject\Http\Requests\DeleteProjectSolutionRequest;
use Illuminate\Support\Facades\Gate;

class AdminDeleteProjectSolutionRequest extends DeleteProjectSolutionRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getProjectSolution());
    }
}
