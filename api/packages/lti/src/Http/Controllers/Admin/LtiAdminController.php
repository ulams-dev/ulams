<?php

namespace Ulams\Lti\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Ulams\Courses\Models\Lesson;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Http\Requests\LtiManageRequest;
use Ulams\Lti\Http\Requests\SavePlatformRequest;
use Ulams\Lti\Http\Requests\SaveToolRequest;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Models\LtiPlatform;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Platform\PlatformLaunchService;
use Ulams\Lti\Support\Lti;

/**
 * Registrations (permission `lti_manage`): tools we launch and platforms that launch us, plus
 * the URLs and key set to give to the other side.
 *
 * @OA\Get(path="/api/admin/lti/endpoints", summary="Our LTI endpoints and issuer, to register us elsewhere", tags={"Admin LTI"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="issuer, JWKS URL, platform and tool endpoints"), @OA\Response(response=403, description="lti_manage required"))
 * @OA\Get(path="/api/admin/lti/tools", summary="List registered tools", tags={"Admin LTI"}, security={{"passport": {}}}, @OA\Response(response=200, description="tools"))
 * @OA\Post(path="/api/admin/lti/tools", summary="Register a tool (we issue client_id and deployment_id)", tags={"Admin LTI"}, security={{"passport": {}}}, @OA\Response(response=201, description="tool"))
 * @OA\Get(path="/api/admin/lti/tools/{tool}", summary="Show a tool", tags={"Admin LTI"}, security={{"passport": {}}},
 *     @OA\Parameter(name="tool", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="tool"))
 * @OA\Put(path="/api/admin/lti/tools/{tool}", summary="Update a tool", tags={"Admin LTI"}, security={{"passport": {}}},
 *     @OA\Parameter(name="tool", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="tool"))
 * @OA\Delete(path="/api/admin/lti/tools/{tool}", summary="Delete a tool without topics", tags={"Admin LTI"}, security={{"passport": {}}},
 *     @OA\Parameter(name="tool", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="deleted"), @OA\Response(response=409, description="used by topics"))
 * @OA\Post(path="/api/admin/lti/tools/{tool}/deep-link", summary="Start deep linking into a lesson", tags={"Admin LTI"}, security={{"passport": {}}},
 *     @OA\Parameter(name="tool", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="{url} to open in a window"))
 * @OA\Get(path="/api/admin/lti/platforms", summary="List platforms that may launch us", tags={"Admin LTI"}, security={{"passport": {}}}, @OA\Response(response=200, description="platforms"))
 * @OA\Post(path="/api/admin/lti/platforms", summary="Register a platform", tags={"Admin LTI"}, security={{"passport": {}}}, @OA\Response(response=201, description="platform"))
 * @OA\Get(path="/api/admin/lti/platforms/{platform}", summary="Show a platform", tags={"Admin LTI"}, security={{"passport": {}}},
 *     @OA\Parameter(name="platform", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="platform"))
 * @OA\Put(path="/api/admin/lti/platforms/{platform}", summary="Update a platform", tags={"Admin LTI"}, security={{"passport": {}}},
 *     @OA\Parameter(name="platform", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="platform"))
 * @OA\Delete(path="/api/admin/lti/platforms/{platform}", summary="Delete a platform", tags={"Admin LTI"}, security={{"passport": {}}},
 *     @OA\Parameter(name="platform", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="deleted"))
 */
class LtiAdminController extends Controller
{
    public function endpoints(LtiManageRequest $request): JsonResponse
    {
        return $this->ok(Lti::endpoints());
    }

    public function tools(LtiManageRequest $request): JsonResponse
    {
        return $this->ok(LtiTool::query()->orderBy('name')->get());
    }

    public function storeTool(SaveToolRequest $request): JsonResponse
    {
        return $this->ok(LtiTool::query()->create($request->validated())->refresh(), 201);
    }

    public function showTool(LtiManageRequest $request, int $tool): JsonResponse
    {
        return $this->ok(LtiTool::query()->findOrFail($tool));
    }

    public function updateTool(SaveToolRequest $request, int $tool): JsonResponse
    {
        $model = LtiTool::query()->findOrFail($tool);
        $model->update($request->validated());

        return $this->ok($model->refresh());
    }

    public function destroyTool(LtiManageRequest $request, int $tool): JsonResponse
    {
        $model = LtiTool::query()->findOrFail($tool);
        if (LtiLink::query()->where('lti_tool_id', $model->getKey())->exists()) {
            return response()->json(['success' => false, 'message' => 'Topics use this tool; disable it instead.'], 409);
        }
        $model->delete();

        return $this->ok(null);
    }

    public function deepLink(LtiManageRequest $request, int $tool, PlatformLaunchService $launches): JsonResponse
    {
        $request->validate(['lesson_id' => ['required', 'integer']]);
        $model = LtiTool::query()->findOrFail($tool);
        $lesson = Lesson::query()->with('course')->findOrFail((int) $request->input('lesson_id'));
        if (!Gate::forUser($request->user())->allows('update', $lesson->course)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            return $this->ok($launches->initiateDeepLinking($model, $lesson, $request->user()));
        } catch (LtiRequestException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }
    }

    public function platforms(LtiManageRequest $request): JsonResponse
    {
        return $this->ok(LtiPlatform::query()->orderBy('name')->get());
    }

    public function storePlatform(SavePlatformRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (LtiPlatform::query()->where('issuer', $data['issuer'])->where('client_id', $data['client_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'This issuer and client_id are already registered.'], 422);
        }

        return $this->ok(LtiPlatform::query()->create($data)->refresh(), 201);
    }

    public function showPlatform(LtiManageRequest $request, int $platform): JsonResponse
    {
        return $this->ok(LtiPlatform::query()->findOrFail($platform));
    }

    public function updatePlatform(SavePlatformRequest $request, int $platform): JsonResponse
    {
        $model = LtiPlatform::query()->findOrFail($platform);
        $model->update($request->validated());

        return $this->ok($model->refresh());
    }

    public function destroyPlatform(LtiManageRequest $request, int $platform): JsonResponse
    {
        LtiPlatform::query()->findOrFail($platform)->delete();

        return $this->ok(null);
    }

    private function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK'], $status);
    }
}
