<?php

namespace EscolaLms\Recommender\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TopicRecommendationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'topic_type' => $this['key'] ? $this['key'] : null,
            'probability' => $this['value'] ? $this['value'] : null,
        ];
    }
}
