<?php

namespace EscolaLms\Tasks\Jobs;

use EscolaLms\Tasks\Events\TaskOverdueEvent;
use EscolaLms\Tasks\Services\Contracts\TaskServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OverdueTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $periodStart;

    private int $periodEnd;

    public function __construct(int $periodStart, int $periodEnd)
    {
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
    }

    public function handle()
    {
        Log::debug("[OverdueTaskJob] running.");

        app(TaskServiceContract::class)
            ->findAllOverdue($this->periodStart, $this->periodEnd)
            ->each(fn ($task) => event(new TaskOverdueEvent($task->user, $task)));
    }
}
