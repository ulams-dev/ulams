<?php

namespace Ulams\PolicyLifecycle\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\Compliance\Services\CsvExporter;
use Ulams\PolicyLifecycle\Services\DashboardService;
use Ulams\PolicyLifecycle\Services\PolicyAccess;
use Ulams\PolicyLifecycle\Services\VariantGenerator;

/**
 * The policy owner's dashboard (ADR 0140).
 *
 * @OA\Get(path="/api/admin/policies/{policy}/dashboard", operationId="admin.policies.by-id.dashboard.get", summary="Policy owner dashboard", tags={"Policies"}, security={{"passport": {}}},
 *     description="Per department: assigned, completed, pending and overdue; per person: the course version taken, the policy version their certificate certifies and the certificate. Counts assignments of the department courses whatever created them.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="variant", in="query", description="department (variant id)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"assigned","overdue","completed","cancelled"})),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=25)),
 *     @OA\Response(response=200, description="departments and one page of people", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy"))
 * @OA\Get(path="/api/admin/policies/{policy}/dashboard/export", operationId="admin.policies.by-id.dashboard.export.get", summary="Export the people of the policy dashboard as CSV", tags={"Policies"}, security={{"passport": {}}},
 *     description="Every matching person as CSV (UTF-8, spreadsheet formulas neutralised). The export is recorded with who exported it, like the compliance exports.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="variant", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"assigned","overdue","completed","cancelled"})),
 *     @OA\Response(response=200, description="text/csv"), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy"))
 */
class DashboardController extends PolicyApiController
{
    private const COLUMNS = ['department', 'user_id', 'name', 'email', 'status', 'assigned_at', 'due_at', 'completed_at', 'course_id', 'course_version', 'policy_version', 'certificate_id', 'certificate_status', 'certificate_expires_at'];

    public function __construct(PolicyAccess $access, private DashboardService $dashboard, private VariantGenerator $generator, private CsvExporter $csv)
    {
        parent::__construct($access);
    }

    public function show(Request $request, string $policy): JsonResponse
    {
        $p = $this->policyFor($request, $policy);
        $filters = $this->filters($request);
        $page = $this->dashboard->paginate($p, $filters, max(1, min(200, (int) $request->input('per_page', 25))));

        return $this->ok([
            'policy' => ['id' => $p->id, 'title' => $p->title, 'currentVersion' => $p->current_version_id !== null ? $p->versions()->whereKey($p->current_version_id)->value('number') : null],
            'departments' => $this->dashboard->departments($p, $this->generator),
            'people' => $page->items(),
        ], 200, ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]);
    }

    public function export(Request $request, string $policy): StreamedResponse
    {
        $p = $this->policyFor($request, $policy);
        $filters = $this->filters($request);
        $userId = $request->user()->getAuthIdentifier();
        $name = sprintf('policy-%s-%s.csv', substr($p->id, -8), now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($p, $filters, $userId) {
            $out = fopen('php://output', 'w');
            $this->csv->header($out, self::COLUMNS);
            $count = 0;
            $this->dashboard->peopleQuery($p, $filters)->chunkById(500, function ($chunk) use ($p, $out, &$count) {
                foreach ($this->dashboard->people($p, $chunk) as $row) {
                    $this->csv->row($out, self::COLUMNS, [
                        'department' => $row['department'],
                        'user_id' => $row['user']['id'] ?? null,
                        'name' => $row['user']['name'] ?? null,
                        'email' => $row['user']['email'] ?? null,
                        'status' => $row['status'],
                        'assigned_at' => $row['assignedAt'],
                        'due_at' => $row['dueAt'],
                        'completed_at' => $row['completedAt'],
                        'course_id' => $row['courseId'],
                        'course_version' => $row['courseVersion'],
                        'policy_version' => $row['policyVersion'],
                        'certificate_id' => $row['certificate']['certificateId'] ?? null,
                        'certificate_status' => $row['certificate']['status'] ?? null,
                        'certificate_expires_at' => $row['certificate']['expiresAt'] ?? null,
                    ]);
                    $count++;
                }
            });
            fclose($out);
            DB::table('compliance_exports')->insert([
                'user_id' => $userId, 'dataset' => 'policy_training', 'format' => 'csv',
                'filters' => json_encode($filters + ['policy_id' => $p->id]), 'row_count' => $count, 'created_at' => now(),
            ]);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** @return array{variant:?string,status:?string} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'variant' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:' . implode(',', DashboardService::STATUSES)],
        ]);

        return ['variant' => $data['variant'] ?? null, 'status' => $data['status'] ?? null];
    }
}
