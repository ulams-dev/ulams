<?php

namespace Ulams\Recommender\Jobs;

use Ulams\Recommender\Models\AggregatedFrame;
use Ulams\Recommender\Services\Contracts\TermAnalyticServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateTermAnalyticJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private AggregatedFrame $aggregatedFrame,
    ) {}

    public function handle(TermAnalyticServiceContract $service): void
    {
        $service->updateTermAnalytic($this->aggregatedFrame->model_type, $this->aggregatedFrame->model_id, $this->aggregatedFrame->term, $this->aggregatedFrame->window_start);
    }
}
