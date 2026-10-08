<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

use EscolaLms\Courses\Facades\Topic;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        $topicable = $this->resource->topicable;

        if (Topic::getResourceClass($this->resource->topicable_type, 'export')) {
            $resourceClass = Topic::getResourceClass($this->resource->topicable_type, 'export');
            $resource = new $resourceClass($this->resource->topicable);
            $topicable = $resource->toArray($request);
        }

        return [
            'title' => $this->resource->title,
            'active' => $this->resource->active,
            'preview' => $this->resource->preview,
            'topicable_type' => $this->resource->topicable_type,
            'topicable' => $topicable,
            'summary' => $this->resource->summary,
            'introduction' => $this->resource->introduction,
            'description' => $this->resource->description,
            'resources' => TopicExportResourceResource::collection($this->resource->resources),
            'order' => $this->resource->order,
            'json' => null,
            'can_skip' => $this->resource->can_skip,
        ];
    }
}
