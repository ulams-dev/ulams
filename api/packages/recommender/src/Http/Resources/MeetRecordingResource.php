<?php

namespace EscolaLms\Recommender\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeetRecordingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'start_at' => $this->resource->start_at,
            'end_at' => $this->resource->end_at,
            'model_type' => $this->resource->model_type,
            'model_id' => $this->resource->model_id,
            'term' => $this->resource->term,
            'url' => $this->resource->is_url_valid ? $this->resource->url : null,
            'url_expires_at' => $this->resource->is_url_valid ? $this->resource->url_expires_at : null,
            'processing_video' => $this->resource->processing_video,
        ];
    }
}
