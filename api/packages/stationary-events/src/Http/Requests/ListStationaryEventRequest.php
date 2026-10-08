<?php

namespace Ulams\StationaryEvents\Http\Requests;

use Ulams\StationaryEvents\Models\StationaryEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListStationaryEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', StationaryEvent::class);
    }

    public function rules(): array
    {
        return [];
    }
}
