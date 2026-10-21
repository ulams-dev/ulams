<?php

namespace Ulams\KnowledgeExport\Services;

use Throwable;
use Ulams\KnowledgeExport\Jobs\ExportKnowledgeJob;
use Ulams\KnowledgeExport\Models\KnowledgeExport;

/**
 * Re-exports a course after its content changed. Only a course that was exported before is kept up to date (an
 * academy that never asked for packs does no extra work). Changes inside the delay window collapse into the one
 * queued run, which reads the content when it starts. A failure here never breaks the save that fired the event.
 */
class ChangeListener
{
    public function topicChanged(object $event): void
    {
        $topic = $event->getTopicContent()->topic ?? null;
        $courseId = $topic?->lesson?->course_id;
        if ($courseId !== null) {
            $this->schedule((int) $courseId);
        }
    }

    public function courseChanged(object $event): void
    {
        $this->schedule((int) $event->getCourse()->getKey());
    }

    private function schedule(int $courseId): void
    {
        try {
            if (!config('knowledge_export.auto')) {
                return;
            }
            $base = KnowledgeExport::query()->where('scope', KnowledgeExport::COURSE)->where('course_id', $courseId);
            if (!(clone $base)->where('status', KnowledgeExport::READY)->exists() || (clone $base)->where('status', KnowledgeExport::QUEUED)->exists()) {
                return;
            }
            $export = KnowledgeExport::query()->create([
                'scope' => KnowledgeExport::COURSE, 'course_id' => $courseId, 'status' => KnowledgeExport::QUEUED, 'trigger' => 'auto',
            ]);
            ExportKnowledgeJob::enqueue($export->id, (int) config('knowledge_export.auto_delay_seconds', 120));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
