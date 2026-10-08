<?php

namespace Ulams\Reports\Http\Controllers\Admin;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Reports\Actions\FindReport;
use Ulams\Reports\Http\Controllers\Admin\Swagger\ReportsSwagger;
use Ulams\Reports\Http\Requests\Admin\ReportRequest;
use Ulams\Reports\Http\Resources\MeasurementCollection;
use Ulams\Reports\Services\Contracts\ReportServiceContract;
use Illuminate\Http\JsonResponse;

class ReportsController extends UlamsBaseController implements ReportsSwagger
{
    private ReportServiceContract $reportService;

    public function __construct(ReportServiceContract $reportService)
    {
        $this->reportService = $reportService;
    }

    public function metrics(): JsonResponse
    {
        return $this->sendResponse(config('reports.metrics'), __('Enabled metrics'));
    }

    public function report(ReportRequest $request, FindReport $action): JsonResponse
    {
        $report = $action->handle($request->getMetric(), $request->getDate(), $request->getLimit());
        if (is_null($report)) {
            return $this->sendError(__("No report found for given date"));
        }
        return $this->sendResponseForResource(MeasurementCollection::make($report->measurements), __("Report data"));
    }

    public function availableForUser(): JsonResponse
    {
        return $this->sendResponse($this->reportService->getAvailableReportsForUser(), __('Available metrics for user'));
    }
}
