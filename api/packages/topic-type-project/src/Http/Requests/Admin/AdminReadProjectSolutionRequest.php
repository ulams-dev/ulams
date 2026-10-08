<?php

namespace EscolaLms\TopicTypeProject\Http\Requests\Admin;

use EscolaLms\TopicTypeProject\Http\Requests\ReadProjectSolutionRequest;
use Illuminate\Support\Facades\Gate;

class AdminReadProjectSolutionRequest extends ReadProjectSolutionRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getProjectSolution());
    }
}
