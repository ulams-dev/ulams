<?php

namespace EscolaLms\TopicTypeProject\Http\Resources\TopicType\Export;

use EscolaLms\TopicTypes\Facades\Markdown;
use EscolaLms\TopicTypes\Facades\Path;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use EscolaLms\TopicTypeProject\Models\Project;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        return [
            'value' => Path::sanitizePathForExport(Markdown::getImagesPathsWithoutImageApi($this->value)),
            'counts_to_grade' => $this->counts_to_grade,
            'weight' => $this->weight,
            'max_score' => $this->max_score,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
