<?php

namespace Ulams\Reports\Http\Requests\Admin;

use Ulams\Cart\Models\Cart;
use Ulams\Reports\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartStatsRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('viewAny', Report::class);
    }

    public function rules()
    {
        return [
            'stats' => ['sometimes', 'array'],
            'stats.*' => ['string', Rule::in(config('reports.stats')[Cart::class] ?? [])],
        ];
    }

    public function getStats(): array
    {
        return $this->has('stats') ? $this->validated()['stats'] : [];
    }
}
