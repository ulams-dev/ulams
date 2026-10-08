<?php

namespace EscolaLms\Recommender\Jobs;

use EscolaLms\Recommender\Models\AggregatedFrame;
use EscolaLms\Recommender\Services\Contracts\TermAnalyticServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildTermAnalyticJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TermAnalyticServiceContract $termAnalyticService): void
    {
        $terms = AggregatedFrame::query()
            ->select('model_type', 'model_id', 'term', 'window_start')
            ->whereNull('term_analytic_id')
            ->get();

        foreach ($terms as $term) {
            $termAnalyticService->updateTermAnalytic($term->model_type, $term->model_id, $term->term, $term->window_start);
        }
    }
}
