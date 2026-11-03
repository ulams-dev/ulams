<?php

namespace Ulams\Ai\Observability;

use Ulams\Ai\Models\AiCall;
use Ulams\Core\Observability\Metric;

/**
 * Tokens and cost of LLM calls per model and task over the last hour and day, from `ai_calls`.
 * Gauges over a sliding window, not counters: they can fall as old calls leave the window.
 */
class AiMetrics
{
    private const WINDOWS = ['1h' => 3600, '1d' => 86400];

    /** @return list<Metric> */
    public function __invoke(): array
    {
        $tokens = [];
        $cost = [];
        $calls = [];
        foreach (self::WINDOWS as $window => $seconds) {
            $rows = AiCall::query()
                ->where('created_at', '>=', now()->subSeconds($seconds))
                ->groupBy('model', 'task')
                ->selectRaw('coalesce(model_served, model_requested) as model, task, sum(input_tokens) as input, sum(output_tokens) as output, sum(cache_read_tokens) as cache_read, sum(cache_creation_tokens) as cache_write, sum(cost_micro_usd) as micro_usd, count(*) as calls')
                ->get();
            foreach ($rows as $row) {
                $labels = ['model' => (string) $row->model, 'task' => (string) $row->task, 'window' => $window];
                foreach (['input', 'output', 'cache_read', 'cache_write'] as $kind) {
                    $tokens[] = [(int) $row->{$kind}, $labels + ['kind' => $kind]];
                }
                $cost[] = [round(((int) $row->micro_usd) / 1_000_000, 6), $labels];
                $calls[] = [(int) $row->calls, $labels];
            }
        }

        return [
            Metric::gauge('ulams_ai_tokens', 'LLM tokens over the window, by model, task and kind.', $tokens),
            Metric::gauge('ulams_ai_cost_usd', 'LLM cost in USD over the window, by model and task.', $cost),
            Metric::gauge('ulams_ai_calls', 'LLM calls over the window, by model and task.', $calls),
        ];
    }
}
