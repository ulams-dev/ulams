<?php

namespace Ulams\CodeExercises\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * Admin and learner view of the topic content. Only the fields below are ever sent: when hidden tests
 * and solutions arrive (verified grading), they stay out of this resource.
 *
 * @mixin CodeExerciseTopic
 */
class CodeExerciseTopicResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'language' => $this->language,
            'runtime' => $this->runtime,
            'grading' => $this->grading,
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
