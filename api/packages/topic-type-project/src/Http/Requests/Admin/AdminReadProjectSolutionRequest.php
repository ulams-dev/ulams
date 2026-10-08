<?php

namespace Ulams\TopicTypeProject\Http\Requests\Admin;

use Ulams\TopicTypeProject\Http\Requests\ReadProjectSolutionRequest;
use Illuminate\Support\Facades\Gate;

class AdminReadProjectSolutionRequest extends ReadProjectSolutionRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getProjectSolution());
    }
}
