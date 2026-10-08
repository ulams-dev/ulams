<?php

namespace EscolaLms\TopicTypeProject\Http\Requests\Admin;

use EscolaLms\TopicTypeProject\Http\Requests\ListProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Support\Facades\Gate;

class AdminListProjectSolutionRequest extends ListProjectSolutionRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', ProjectSolution::class);
    }
}
