<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Services\ComplianceReportService;
use Ulams\Compliance\Services\CsvExporter;

/**
 * Compliance report and audit export. Permission `compliance_report`. Datasets: `assignments` (required
 * training and its outcome), `certificates` (every certificate with the course version it attests) and `audit`
 * (hash-chained Living Course audit entries, when that package is installed).
 *
 * @OA\Get(path="/api/admin/compliance/report", summary="Compliance report", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="dataset", in="query", required=true, @OA\Schema(type="string", enum={"assignments","certificates","audit"})),
 *     @OA\Parameter(name="course_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="status", in="query", description="assignments: assigned, overdue, completed, cancelled; certificates: valid, expired, revoked", @OA\Schema(type="string")),
 *     @OA\Parameter(name="from", in="query", description="assigned or issued on or after this date", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", description="assigned or issued on or before this date", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=50)),
 *     @OA\Response(response=200, description="columns and rows of the dataset, with pagination meta"), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Unknown dataset"))
 * @OA\Get(path="/api/admin/compliance/report/export", summary="Audit export of a compliance dataset", tags={"Compliance"}, security={{"passport": {}}},
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
    public function __construct(private ComplianceReportService $reports, private CsvExporter $csv)
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
        $columns = $this->reports->columns($dataset);
        $query = $this->reports->query($dataset, $filters);
        $userId = $request->user()->getKey();
        $name = sprintf('compliance-%s-%s.%s', $dataset, now()->format('Ymd-His'), $format);

        return response()->streamDownload(function () use ($query, $dataset, $columns, $format, $filters, $userId) {
            $out = fopen('php://output', 'w');
            $count = 0;
            if ($format === 'csv') {
                $this->csv->header($out, $columns);
            }
            $query->chunkById(500, function ($records) use ($out, $dataset, $columns, $format, &$count) {
                foreach ($records as $record) {
                    $row = $this->reports->row($dataset, $record);
                    if ($format === 'csv') {
                        $this->csv->row($out, $columns, $row);
                    } else {
                        fwrite($out, json_encode(array_intersect_key($row, array_flip($columns)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                    }
                    $count++;
                }
            });
            fclose($out);
            DB::table('compliance_exports')->insert([
                'user_id' => $userId,
                'dataset' => $dataset,
                'format' => $format,
                'filters' => json_encode($filters),
                'row_count' => $count,
                'created_at' => now(),
            ]);
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
