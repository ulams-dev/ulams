<?php

namespace Ulams\StationaryEvents\Http\Requests;

use Ulams\StationaryEvents\Models\StationaryEvent;
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
