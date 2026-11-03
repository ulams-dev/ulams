<?php

namespace Ulams\CodeExercises\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\CodeExercises\Models\CodeExerciseAttempt;

/** @mixin CodeExerciseAttempt */
class CodeExerciseAttemptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'attempt_no' => $this->attempt_no,
            'mode' => $this->mode,
            'verified' => $this->verified,
            'passed' => $this->passed,
            'total' => $this->total,
            'score' => $this->score,
            'results' => $this->results,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
