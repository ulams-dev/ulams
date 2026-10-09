<?php

namespace Ulams\Interactive\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * Course export (packages/courses-import-export): the package travels with the course.
 * InteractiveTopic::fixAssetPaths() writes the played version's files to topic/<id>/interactive/;
 * InteractiveTopicImportStrategy creates a new package from that folder on import. The keys are merged
 * into the topic data by the importer, so the package fields are prefixed; there is no package id or
 * pinned version, which mean nothing in another tenant (an imported topic pins the new package's v1).
 *
 * @mixin InteractiveTopic
 */
class InteractiveTopicExportResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        $topic = $this->resource->topic;
        $version = $this->resolveVersion();

        return [
            'interactive_folder' => sprintf('topic/%d/%s', $topic?->getKey(), InteractiveTopic::EXPORT_FOLDER),
            'interactive_title' => $this->package?->title,
            'interactive_version' => $version?->version,
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
