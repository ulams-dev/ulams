<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Services\DashboardService;

/**
 * Compliance dashboard numbers. Permission `compliance_report`.
 *
 * @OA\Get(path="/api/admin/compliance/dashboard", operationId="admin.compliance.dashboard.get", summary="Compliance dashboard", tags={"Compliance"}, security={{"passport": {}}},
 *     description="Required training (open, overdue, completed, completion rate, average days to complete), certificate health (valid, expiring in 30 and 90 days, expired, revoked) and the same per course, the most overdue first.",
 *     @OA\Parameter(name="course_id", in="query", description="Limit to one course", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="assignments, certificates and courses blocks", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 */
class DashboardController extends ComplianceController
{
    public function __construct(private DashboardService $dashboard)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::REPORT);
        $data = $request->validate(['course_id' => ['sometimes', 'integer']]);

        return $this->ok($this->dashboard->summary($data['course_id'] ?? null));
    }
}
