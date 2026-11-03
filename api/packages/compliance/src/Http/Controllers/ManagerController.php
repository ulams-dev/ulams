<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Models\ManagerLink;
use Ulams\Compliance\Services\ManagerService;
use Illuminate\Validation\ValidationException;
use Ulams\Core\Models\User;

/**
 * Who escalations of a user's overdue training go to. Permission `compliance_manage`. Until an HRIS
 * import exists (Phase 6.3) the links are maintained here.
 *
 * @OA\Get(path="/api/admin/compliance/managers", operationId="admin.compliance.managers.get", summary="List manager links", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="user_id and manager_id pairs", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Put(path="/api/admin/compliance/managers/{user}", operationId="admin.compliance.managers.by-user.put", summary="Set or clear the manager of a user", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", @OA\Property(property="manager_id", type="integer", nullable=true)))),
 *     @OA\Response(response=200, description="the link, or null when cleared", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Validation error"))
 */
class ManagerController extends ComplianceController
{
    public function __construct(private ManagerService $managers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);

        return $this->ok(ManagerLink::query()->orderBy('user_id')->get(['user_id', 'manager_id']));
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        User::query()->findOrFail($user);
        $data = $request->validate(['manager_id' => ['present', 'nullable', 'integer', 'exists:users,id']]);
        if ($data['manager_id'] === $user) {
            throw ValidationException::withMessages(['manager_id' => 'A user cannot be their own manager.']);
        }

        $link = $this->managers->link($user, $data['manager_id']);

        return $this->ok($link ? ['user_id' => $link->user_id, 'manager_id' => $link->manager_id] : null);
    }
}
