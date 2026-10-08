<?php

namespace Ulams\TopicTypeProject\Http\Resources\TopicType\Export;

use Ulams\TopicTypes\Facades\Markdown;
use Ulams\TopicTypes\Facades\Path;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Ulams\TopicTypeProject\Models\Project;
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
