<?php

namespace Ulams\LiaScript\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * Course export (packages/courses-import-export): the course text travels with the course.
 * LiaScriptTopic::fixAssetPaths() writes the current version (README.md and its assets) to
 * topic/<id>/liascript/ in the export; LiaScriptTopicImportStrategy creates a new document from
 * that folder on import. Keys are prefixed because the importer merges them into the topic data
 * (a plain `title` would replace the topic's title); there is no `id` or document `value`, which
 * mean nothing in another tenant.
 *
 * @mixin LiaScriptTopic
 */
class LiaScriptTopicExportResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        $topic = $this->resource->topic;

        return [
            'liascript_folder' => sprintf('topic/%d/%s', $topic?->getKey(), LiaScriptTopic::EXPORT_FOLDER),
            'liascript_title' => $this->document?->title,
            'liascript_version' => $this->document?->current_version,
        ];
    }
}
