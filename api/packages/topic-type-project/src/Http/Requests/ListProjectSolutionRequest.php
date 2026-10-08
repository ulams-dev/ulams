<?php

namespace EscolaLms\TopicTypeProject\Http\Requests;

use EscolaLms\TopicTypeProject\Dtos\CriteriaDto;
use EscolaLms\TopicTypeProject\Dtos\PageDto;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
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
