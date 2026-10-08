<?php

namespace App\Http\Resources;

use Ulams\StationaryEvents\Http\Resources\StationaryEventResource;
use Ulams\StationaryEvents\Models\StationaryEvent;
use Ulams\Webinar\Http\Resources\WebinarSimpleResource;
use Ulams\Webinar\Models\Webinar;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray($request)
    {
        $fields = $this->event_type === StationaryEvent::class
            ? StationaryEventResource::make(StationaryEvent::find($this->event_id))->toArray($request)
            : WebinarSimpleResource::make(Webinar::find($this->event_id))->toArray($request);
        return array_merge($fields, [
            'model' => $this->event_type,
        ]);
    }
}
