<?php

namespace EscolaLms\Reports\Services;

use EscolaLms\Reports\Metrics\AbstractMetric;
use EscolaLms\Reports\Services\Contracts\ReportServiceContract;

class ReportService implements ReportServiceContract
{
    public function getAvailableReportsForUser(): array
    {
        $availableMetrics = config('reports.metrics', []);
        return array_filter(
            $availableMetrics,
            fn (string $metric) => class_exists($metric) && is_a($metric, AbstractMetric::class, true)
                && $metric::requiredPackageInstalled()
                && $metric::requiredPermissionsCheck()
        );
    }
}
