<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Services\SkillProfileService;
use Ulams\Core\Models\User;

/**
 * Learner skill profiles: the confirmed competencies of the courses a learner has done, with when they were earned
 * and when they lapse.
 *
 * @OA\Get(path="/api/compliance/my-skills", operationId="compliance.my-skills.get", summary="My skill profile", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="competencies with the courses that prove them, earned_at and expires_at", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 * @OA\Get(path="/api/admin/compliance/users/{user}/skills", operationId="admin.compliance.users.by-user.skills.get", summary="The skill profile of a user", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="competencies", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown user"))
 */
class SkillsController extends ComplianceController
{
    public function __construct(private SkillProfileService $skills)
    {
    }

    public function mine(Request $request): JsonResponse
    {
        return $this->ok($this->skills->forUser(User::query()->findOrFail($request->user()->getKey())));
    }

    public function user(Request $request, int $user): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::REPORT);

        return $this->ok($this->skills->forUser(User::query()->findOrFail($user)));
    }
}
