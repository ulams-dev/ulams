<?php

namespace EscolaLms\Reports\Actions;

use EscolaLms\Reports\Metrics\Contracts\MetricContract;
use EscolaLms\Reports\Models\Report;
use Exception;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class FindReport
{
    public function handle(string $metric_class, ?Carbon $date = null, ?int $limit = null): ?Report
    {
        if (!class_exists($metric_class) || !is_a($metric_class, MetricContract::class, true)) {
            throw new InvalidArgumentException(__('Metric must be insance of :contract . :metric given.', [
                'contract' => MetricContract::class,
                'metric' => $metric_class
            ]));
        }

        /** @var MetricContract $metric */
        $metric = $metric_class::make();

        if (!$metric->requiredPackageInstalled()) {
            throw new Exception(__('This Metric requires :package package(s) installed', ['package' => $metric->requiredPackage()]));
        }

        /** @var Report $report */
        $report = Report::where('metric', $metric_class)->whereDate('created_at', '=', $date ?? Carbon::now())->orderBy('created_at', 'DESC')->first();

        if ($this->isHistorical($date) || $this->hasEnoughDataPoints($date, $report, $limit)) {
            return $report;
        }

        return $metric->calculateAndStore($limit);
    }

    private function isHistorical(?Carbon $date): bool
    {
        return !is_null($date) && !$date->isSameDay(Carbon::now());
    }

    private function hasEnoughDataPoints(?Carbon $date, ?Report $report, ?int $limit)
    {
        return !is_null($date) && !is_null($report) && $report->measurements()->count() >= (int) $limit;
    }
}
