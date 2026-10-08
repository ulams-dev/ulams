<?php

namespace Ulams\Tasks\Http\Requests\Admin;

use Ulams\Tasks\Dtos\CriteriaDto;
use Ulams\Tasks\Dtos\OrderDto;
use Ulams\Tasks\Dtos\PageDto;
use Ulams\Tasks\Models\Task;
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
