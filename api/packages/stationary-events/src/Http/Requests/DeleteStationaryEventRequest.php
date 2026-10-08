<?php

namespace EscolaLms\StationaryEvents\Http\Requests;

use EscolaLms\StationaryEvents\Models\StationaryEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteStationaryEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getStationaryEvent());
    }

    public function rules(): array
    {
        return [];
    }

    public function getStationaryEvent(): StationaryEvent
    {
        return StationaryEvent::findOrFail($this->route('id'));
    }
}
