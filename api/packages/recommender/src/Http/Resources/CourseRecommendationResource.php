<?php

namespace EscolaLms\Recommender\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CourseRecommendationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'probability' => $this['value'] ? $this['value'] : null,
        ];
    }
}
