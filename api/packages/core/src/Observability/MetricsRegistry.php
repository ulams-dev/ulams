<?php

namespace Ulams\Core\Observability;

use Illuminate\Support\Facades\Log;

/**
 * Collectors register themselves here (core, tenancy, ai, ...); `GET /metrics` renders what they return
 * at scrape time. Nothing is stored between scrapes, so PHP-FPM needs no per-request counters. A collector
 * that throws is reported in `ulams_collector_up` and skipped, so one broken source never hides the rest.
 */
class MetricsRegistry
{
    /** @var array<string, callable(): iterable<Metric>> */
    private array $collectors = [];

    /** @param callable(): iterable<Metric> $collector */
    public function register(string $name, callable $collector): void
    {
        $this->collectors[$name] = $collector;
    }

    /** @return list<Metric> */
    public function collect(): array
    {
        $metrics = [];
        $status = [];
        foreach ($this->collectors as $name => $collector) {
            try {
                foreach ($collector() as $metric) {
                    $metrics[] = $metric;
                }
                $status[] = [1, ['collector' => $name]];
            } catch (\Throwable $e) {
                Log::warning('Metrics collector failed', ['collector' => $name, 'error' => $e->getMessage()]);
                $status[] = [0, ['collector' => $name]];
            }
        }
        $metrics[] = Metric::gauge('ulams_collector_up', '1 when the collector ran without error at this scrape.', $status);

        return $metrics;
    }

    public function render(): string
    {
        return PrometheusText::render($this->collect());
    }
}
