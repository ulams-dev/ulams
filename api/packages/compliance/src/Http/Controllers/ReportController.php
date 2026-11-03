<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Services\ComplianceReportService;

/**
 * Compliance report and audit export. Permission `compliance_report`. Datasets: `assignments` (required
 * training and its outcome), `certificates` (every certificate with the course version it attests) and `audit`
 * (hash-chained Living Course audit entries, when that package is installed).
 *
 * @OA\Get(path="/api/admin/compliance/report", operationId="admin.compliance.report.get", summary="Compliance report", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="dataset", in="query", required=true, @OA\Schema(type="string", enum={"assignments","certificates","audit"})),
 *     @OA\Parameter(name="course_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="status", in="query", description="assignments: assigned, overdue, completed, cancelled; certificates: valid, expired, revoked", @OA\Schema(type="string")),
 *     @OA\Parameter(name="from", in="query", description="assigned or issued on or after this date", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", description="assigned or issued on or before this date", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=50)),
 *     @OA\Response(response=200, description="columns and rows of the dataset, with pagination meta", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Unknown dataset"))
 * @OA\Get(path="/api/admin/compliance/report/export", operationId="admin.compliance.report.export.get", summary="Audit export of a compliance dataset", tags={"Compliance"}, security={{"passport": {}}},
 *     description="Streams every matching row as CSV (default) or JSON lines. The export itself is recorded (who, dataset, filters, row count).",
 *     @OA\Parameter(name="dataset", in="query", required=true, @OA\Schema(type="string", enum={"assignments","certificates","audit"})),
 *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"csv","jsonl"}, default="csv")),
 *     @OA\Parameter(name="course_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="file download"), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Unknown dataset"))
 */
class ReportController extends ComplianceController
{
    public function __construct(private ComplianceReportService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::REPORT);
        [$dataset, $filters] = $this->input($request);

        $page = $this->reports->query($dataset, $filters)->paginate(max(1, min(200, (int) $request->input('per_page', 50))));
        $rows = collect($page->items())->map(fn ($record) => $this->reports->row($dataset, $record))->values();

        return $this->ok(
            ['columns' => $this->reports->columns($dataset), 'rows' => $rows],
            200,
            ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->guard($request, CompliancePermissionsEnum::REPORT);
        [$dataset, $filters] = $this->input($request);
        $format = $request->input('format', 'csv') === 'jsonl' ? 'jsonl' : 'csv';
        $userId = $request->user()->getKey();
        $name = sprintf('compliance-%s-%s.%s', $dataset, now()->format('Ymd-His'), $format);

        return response()->streamDownload(function () use ($dataset, $filters, $format, $userId) {
            $out = fopen('php://output', 'w');
            $this->reports->write($dataset, $filters, $format, $out, $userId);
            fclose($out);
        }, $name, ['Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/x-ndjson', 'Cache-Control' => 'no-store']);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'dataset' => ['required', 'string'],
            'course_id' => ['sometimes', 'integer'],
            'user_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', 'max:16'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);
        abort_unless($this->reports->available($data['dataset']), 422, 'Unknown or unavailable dataset.');

        return [$data['dataset'], $this->reports->normalise($data)];
    }
}
