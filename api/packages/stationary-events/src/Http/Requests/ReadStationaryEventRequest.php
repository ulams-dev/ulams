<?php

namespace EscolaLms\StationaryEvents\Http\Requests;

use EscolaLms\StationaryEvents\Models\StationaryEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use EscolaLms\StationaryEvents\Exceptions\StationaryEventNotFoundException;

class ReadStationaryEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getStationaryEvent());
    }

    public function rules(): array
    {
        return [];
    }

    public function getStationaryEvent(): StationaryEvent
    {
        $stationaryEvent = StationaryEvent::find($this->route('id'));

        if (!$stationaryEvent) {
            throw new StationaryEventNotFoundException();
        }

        return $stationaryEvent;
    }
}
