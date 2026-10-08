<?php

namespace EscolaLms\Tasks\Http\Requests\Admin;

use EscolaLms\Tasks\Dtos\CriteriaDto;
use EscolaLms\Tasks\Dtos\OrderDto;
use EscolaLms\Tasks\Dtos\PageDto;
use EscolaLms\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminListTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', Task::class);
    }

    public function rules(): array
    {
        return [];
    }

    public function getCriteria(): CriteriaDto
    {
        return CriteriaDto::instantiateFromRequest($this);
    }

    public function getPage(): PageDto
    {
        return PageDto::instantiateFromRequest($this);
    }

    public function getOrder(): OrderDto
    {
        return OrderDto::instantiateFromRequest($this);
    }
}
