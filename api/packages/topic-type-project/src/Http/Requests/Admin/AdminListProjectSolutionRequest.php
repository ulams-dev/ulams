<?php

namespace Ulams\TopicTypeProject\Http\Requests\Admin;

use Ulams\TopicTypeProject\Http\Requests\ListProjectSolutionRequest;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Support\Facades\Gate;

class AdminListProjectSolutionRequest extends ListProjectSolutionRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', ProjectSolution::class);
    }
}
