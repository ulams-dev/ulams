<?php

namespace Ulams\Compliance\Http;

use Ulams\Compliance\Models\Assignment;

/** The array shape of an assignment in every compliance response. */
class AssignmentPresenter
{
    public static function toArray(Assignment $assignment): array
    {
        $certificate = $assignment->certificate;

        return [
            'id' => $assignment->getKey(),
            'user' => $assignment->user ? ['id' => $assignment->user->getKey(), 'name' => $assignment->user->name, 'email' => $assignment->user->email] : null,
            'course' => $assignment->course ? ['id' => $assignment->course->getKey(), 'title' => $assignment->course->title] : null,
            'source' => $assignment->source,
            'status' => $assignment->displayStatus(),
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'due_at' => $assignment->due_at?->toIso8601String(),
            'completed_at' => $assignment->completed_at?->toIso8601String(),
            'escalation_level' => $assignment->escalation_level,
            'certificate' => $certificate ? [
                'id' => $certificate->getKey(),
                'certificate_id' => $certificate->certificate_id,
                'status' => $certificate->status(),
                'expires_at' => $certificate->expires_at?->toIso8601String(),
            ] : null,
        ];
    }
}
