<?php

namespace Ulams\CodeExercises\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * Course export: plain JSON, so the import creates the topic through the topic API, which validates
 * the files again (an export is untrusted input too).
 *
 * @mixin CodeExerciseTopic
 */
class CodeExerciseTopicExportResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        return [
            'language' => $this->language,
            'files' => $this->files,
            'visible_tests' => $this->visible_tests,
            'timeout_ms' => $this->timeout_ms,
            'completion_rule' => $this->completion_rule,
            'pass_score' => $this->pass_score,
            'text' => $this->text,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
