<?php

namespace Ulams\Recommender\Jobs;

use Ulams\Recommender\Enum\SatisfactionStatusEnum;
use Ulams\Recommender\Models\TermAnalytic;
use Ulams\Recommender\Services\Contracts\TermAnalyticServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PredictSatisfactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private TermAnalytic $termAnalytic
    ) {}

    public function handle(TermAnalyticServiceContract $termAnalyticService): void
    {
        $this->termAnalytic->update([
            'satisfaction_status' => SatisfactionStatusEnum::SENDING,
        ]);

        $termAnalyticService->predictSatisfaction($this->termAnalytic);
    }

    public function failed(\Throwable $exception): void
    {
        $this->termAnalytic->update([
            'satisfaction_status' => SatisfactionStatusEnum::FAILED,
        ]);

        Log::error('Predict satisfaction job failed', ['id' => $this->termAnalytic->getKey(), 'error' => $exception->getMessage()]);
    }
}
