<?php

namespace EscolaLms\Reports\Http\Requests\Admin;

use EscolaLms\Reports\Models\Report;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReportRequest extends AbstractAdminOnlyRequest
{
    public function authorize()
    {
        return $this->user()->can('viewAny', Report::class)
            && $this->getMetric()::requiredPermissionsCheck();
    }

    public function rules()
    {
        return [
            'metric' => ['required', Rule::in(config('reports.metrics'))],
            'limit' => ['sometimes', 'integer'],
            'date' => ['sometimes', 'date'],
        ];
    }

    public function getMetric(): string
    {
        return $this->input('metric');
    }

    public function getDate(): ?Carbon
    {
        if ($this->has('date')) {
            return Carbon::parse($this->validated()['date']);
        }
        return null;
    }

    public function getLimit(): ?int
    {
        if ($this->has('limit')) {
            return $this->validated()['limit'];
        }
        return null;
    }
}
