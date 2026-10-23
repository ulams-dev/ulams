<?php

namespace Ulams\Reporting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\Reporting\Contracts\ReportDataset;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * The datasets a report can be built on (ADR 0194, plan docs/plans/authoring-metric-and-report-builder.md).
 *
 * @OA\Get(path="/api/admin/reporting/datasets", operationId="admin.reporting.datasets.list", summary="Datasets a report can be built on", tags={"Reporting"}, security={{"passport": {}}},
 *     description="The datasets the caller may run reports on (assignments, certificates, audit, authoring, ai_usage; each only when its package is installed and the caller holds the dataset's permission), with every column: type, label, the filter operators it accepts, whether it can be grouped or aggregated and whether it holds personal data. Needs the permission reporting_read.",
 *     @OA\Response(response=200, description="The datasets with their columns", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 */
class DatasetController extends Controller
{
    public function __construct(private readonly DatasetRegistry $registry)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can(ReportingPermissionsEnum::READ, 'api'), 403);
        $context = new ReportContext($request->user());

        $datasets = array_values(array_map(fn (ReportDataset $d) => [
            'key' => $d->key(),
            'label' => $d->label(),
            'description' => $d->description(),
            'permission' => $d->permission(),
            'subject_column' => $d->subjectColumn(),
            'aggregate_only' => $d->aggregateOnly(),
            'date_columns' => $d->dateColumns(),
            'columns' => array_map(fn (Column $c) => $c->toArray(), $d->columns()),
        ], $this->registry->runnableBy($context)));

        return response()->json(['success' => true, 'data' => $datasets, 'message' => 'OK']);
    }
}
