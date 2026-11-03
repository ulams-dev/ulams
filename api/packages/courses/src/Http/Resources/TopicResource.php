<?php

namespace Ulams\Courses\Http\Resources;

use Ulams\Courses\Facades\Topic;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
{
    /** @var array<string,callable(\Ulams\Courses\Models\Topic):array<string,mixed>> */
    private static array $extensions = [];

    /**
     * Lets another package add fields to a topic in program and preview responses (for example
     * `ai_assisted`). The callback gets the topic and returns extra keys; it cannot replace the core ones.
     * A name registers once: extending again under the same name replaces the earlier callback.
     *
     * @param callable(\Ulams\Courses\Models\Topic):array<string,mixed> $extension
     */
    public static function extend(string $name, callable $extension): void
    {
        self::$extensions[$name] = $extension;
    }

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

        if ($this->resource->lesson && !$this->resource->lesson->isActive()) {
            $topicable = null;
        } elseif (Topic::getResourceClass($this->resource->topicable_type, 'client')) {
            $resourceClass = Topic::getResourceClass($this->resource->topicable_type, 'client');
            $resource = new $resourceClass($this->resource->topicable);
            $topicable = $resource->toArray($request);
        }

        $extra = [];
        foreach (self::$extensions as $extension) {
            $extra = array_merge($extra, $extension($this->resource));
        }

        return [
            ...$extra,
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'lesson_id' => $this->resource->lesson_id,
            'active' => $this->resource->active,
            'preview' => $this->resource->preview,
            'topicable_id' => $this->resource->topicable_id,
            'topicable_type' => $this->resource->topicable_type,
            'topicable' => $topicable,
            'summary' => $this->resource->summary,
            'introduction' => $this->resource->introduction,
            'description' => $this->resource->description,
            'resources' => TopicResourceResource::collection($this->resource->resources),
            'order' => $this->resource->order,
            'json' => $this->resource->json,
            'can_skip' => $this->resource->can_skip,
            'duration' => $this->resource->duration,
        ];
    }
}
