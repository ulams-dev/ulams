<?php

namespace EscolaLms\StationaryEvents\Http\Requests;

use EscolaLms\StationaryEvents\Models\StationaryEvent;
use Illuminate\Foundation\Http\FormRequest;

class ReadStationaryEventPublicRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function getStationaryEvent(): StationaryEvent
    {
        return StationaryEvent::findOrFail($this->route('id'));
    }
}
