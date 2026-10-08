<?php

namespace Ulams\TopicTypeProject\Http\Requests;

use Ulams\TopicTypeProject\Dtos\CriteriaDto;
use Ulams\TopicTypeProject\Dtos\PageDto;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListProjectSolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('listOwn', ProjectSolution::class);
    }

    public function rules(): array
    {
        return [];
    }

    public function getPage(): PageDto
    {
        return PageDto::instantiateFromRequest($this);
    }

    public function getCriteria(): CriteriaDto
    {
        return CriteriaDto::instantiateFromRequest($this);
    }
}
