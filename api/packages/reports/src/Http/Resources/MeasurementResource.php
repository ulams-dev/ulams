<?php

namespace EscolaLms\Reports\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'measurable_id' => $this->measurable_id,
            'measurable_type' => $this->measurable_type,
        ];
    }
}
