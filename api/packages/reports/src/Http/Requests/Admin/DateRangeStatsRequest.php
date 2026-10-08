<?php

namespace EscolaLms\Reports\Http\Requests\Admin;

use Carbon\Carbon;
use EscolaLms\Reports\Models\Report;
use EscolaLms\Reports\ValueObject\DateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DateRangeStatsRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('viewAny', Report::class);
    }

    public function rules()
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'stats' => ['sometimes', 'array'],
            'stats.*' => ['string', Rule::in(config('reports.stats')[DateRange::class] ?? [])],
        ];
    }

    public function getDateFrom(): ?string
    {
        return $this['date_from'] ? $this->validated()['date_from'] : null;
    }

    public function getDateTo(): ?string
    {
        return $this['date_to'] ? $this->validated()['date_to'] : null;
    }

    public function getDateRange(): DateRange
    {
        $dateFrom = Carbon::parse($this->getDateFrom());
        $dateTo = Carbon::parse($this->getDateTo());

        return new DateRange($dateFrom, $dateTo);
    }

    public function getStats(): array
    {
        return $this->has('stats') ? $this->validated()['stats'] : [];
    }
}
