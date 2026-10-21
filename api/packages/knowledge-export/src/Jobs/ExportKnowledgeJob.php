<?php

namespace Ulams\KnowledgeExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Ulams\KnowledgeExport\Models\KnowledgeExport;
use Ulams\KnowledgeExport\Services\KnowledgeExporter;

/** Writes one pack. The export row exists before the job is queued, so the API can show it as queued. */
class ExportKnowledgeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly string $exportId)
    {
    }

    public static function enqueue(string $exportId, int $delaySeconds = 0): void
    {
        $job = new self($exportId);
        // the long-job connection has a retry_after above $timeout (ADR 0083)
        if ($connection = config('queue.long_job.connection')) {
            $job->onConnection($connection);
        }
        if ($queue = config('queue.long_job.queue')) {
            $job->onQueue($queue);
        }
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }
        dispatch($job);
    }

    public function handle(KnowledgeExporter $exporter): void
    {
        $export = KnowledgeExport::query()->find($this->exportId);
        if ($export !== null && $export->status === KnowledgeExport::QUEUED) {
            $exporter->execute($export);
        }
    }
}
