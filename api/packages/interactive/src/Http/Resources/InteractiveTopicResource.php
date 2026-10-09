<?php

namespace Ulams\Interactive\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * @mixin InteractiveTopic
 */
class InteractiveTopicResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        $version = $this->resolveVersion();

        return [
            'id' => $this->id,
            'value' => $this->value,
            'title' => $this->package?->title,
            'version' => $this->version,
            'follow_latest' => $this->follow_latest,
            'resolved_version' => $version?->version,
            'start_step' => $this->start_step,
            'end_step' => $this->end_step,
            'completion_rule' => $this->completion_rule,
            'pass_score' => $this->pass_score,
            'display' => $this->display,
            'height' => $this->height,
            'text' => $this->text,
        ];
    }
}
