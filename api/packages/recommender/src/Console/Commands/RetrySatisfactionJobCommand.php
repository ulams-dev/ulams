<?php

namespace EscolaLms\Recommender\Console\Commands;

use EscolaLms\Recommender\Enum\SatisfactionStatusEnum;
use EscolaLms\Recommender\Jobs\PredictSatisfactionJob;
use EscolaLms\Recommender\Models\TermAnalytic;
use Illuminate\Console\Command;

class RetrySatisfactionJobCommand extends Command
{
    protected $signature = 'recommender:retry-satisfaction {id? : ID TermAnalytic}';
    protected $description = 'Retry sent predict satisfaction jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');;

        if ($id) {
            $terms = TermAnalytic::query()->where('id', $id)->get();
        } else {
            $terms = TermAnalytic::query()->whereIn('satisfaction_status', [SatisfactionStatusEnum::FAILED, SatisfactionStatusEnum::SENDING])->get();
        }

        if ($terms->isEmpty()) {
            $this->info('No results found');
            return;
        }

        foreach ($terms as $term) {
            $this->info('Predict satisfaction job: ' . $term->getKey());
            PredictSatisfactionJob::dispatch($term);
        }

        $this->info('Queued ' . count($terms) . ' satisfaction jobs');
    }
}
