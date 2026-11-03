<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Models\LearningPath;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Compliance\Services\PathEnrollmentService;
use Ulams\Compliance\Services\PathService;
use Ulams\Core\Models\User;

/**
 * Learning paths: ordered courses with prerequisites. Admin endpoints need `compliance_manage`;
 * `my-paths` is the learner's own view.
 *
 * @OA\Get(path="/api/admin/compliance/paths", operationId="admin.compliance.paths.get", summary="List learning paths with their steps", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="paths with steps (course_id, position, prerequisites)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/compliance/paths", operationId="admin.compliance.paths.post", summary="Create a learning path", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"title"},
 *         @OA\Property(property="title", type="string"), @OA\Property(property="description", type="string", nullable=true),
 *         @OA\Property(property="steps", type="array", description="Ordered; prerequisites are course ids of this path", @OA\Items(type="object",
 *             @OA\Property(property="course_id", type="integer"), @OA\Property(property="prerequisites", type="array", @OA\Items(type="integer"))))))),
 *     @OA\Response(response=201, description="the path", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Unknown course, foreign prerequisite or a cycle"))
 * @OA\Get(path="/api/admin/compliance/paths/{id}", operationId="admin.compliance.paths.by-id.get", summary="Show a learning path", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the path with enrolment counts", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown path"))
 * @OA\Put(path="/api/admin/compliance/paths/{id}", operationId="admin.compliance.paths.by-id.put", summary="Update a learning path (steps are replaced when sent)", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the path", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=422, description="Validation error"))
 * @OA\Delete(path="/api/admin/compliance/paths/{id}", operationId="admin.compliance.paths.by-id.delete", summary="Delete a learning path (its assignments stay)", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="deleted", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown path"))
 * @OA\Post(path="/api/admin/compliance/paths/{id}/enrollments", operationId="admin.compliance.paths.by-id.enrollments.post", summary="Enrol users in a learning path", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"user_ids"},
 *         @OA\Property(property="user_ids", type="array", @OA\Items(type="integer")), @OA\Property(property="due_at", type="string", format="date-time", nullable=true)))),
 *     @OA\Response(response=201, description="enrolments (a user already in the path keeps theirs)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Get(path="/api/compliance/my-paths", operationId="compliance.my-paths.get", summary="My learning paths", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="each enrolment with the path steps and their status (done, available, locked)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 */
class PathController extends ComplianceController
{
    public function __construct(private PathService $paths, private PathEnrollmentService $enrollments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);

        return $this->ok(LearningPath::query()->with('steps')->withCount('enrollments')->orderBy('title')->get()->map(fn (LearningPath $p) => $this->present($p))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);

        return $this->ok($this->present($this->paths->save($this->validated($request, true))), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);

        return $this->ok($this->present(LearningPath::query()->with('steps')->withCount('enrollments')->findOrFail($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $path = LearningPath::query()->findOrFail($id);

        return $this->ok($this->present($this->paths->save($this->validated($request, false) + ['title' => $path->title], $path)));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        LearningPath::query()->findOrFail($id)->delete();

        return $this->ok(null);
    }

    public function enroll(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $path = LearningPath::query()->findOrFail($id);
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);
        $due = isset($data['due_at']) ? Carbon::parse($data['due_at']) : null;

        $created = User::query()->whereIn('id', $data['user_ids'])->get()
            ->map(fn (User $user) => $this->enrollments->enroll($user, $path, $due));

        return $this->ok($created->map(fn (PathEnrollment $e) => [
            'id' => $e->getKey(), 'user_id' => $e->user_id, 'path_id' => $e->path_id, 'source' => $e->source,
            'due_at' => $e->due_at?->toIso8601String(), 'completed_at' => $e->completed_at?->toIso8601String(),
        ])->values(), 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = User::query()->findOrFail($request->user()->getKey());
        $enrollments = PathEnrollment::query()->with('path')->where('user_id', $user->getKey())->latest('id')->get();

        return $this->ok($enrollments->map(fn (PathEnrollment $e) => [
            'path_id' => $e->path_id,
            'title' => $e->path?->title,
            'source' => $e->source,
            'enrolled_at' => $e->enrolled_at?->toIso8601String(),
            'due_at' => $e->due_at?->toIso8601String(),
            'completed_at' => $e->completed_at?->toIso8601String(),
            'steps' => $e->path ? $this->enrollments->stepStatuses($user, $e->path) : [],
        ])->values());
    }

    private function validated(Request $request, bool $titleRequired): array
    {
        return $request->validate([
            'title' => [$titleRequired ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'steps' => ['sometimes', 'array', 'max:100'],
            'steps.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'steps.*.prerequisites' => ['sometimes', 'array', 'max:100'],
            'steps.*.prerequisites.*' => ['integer'],
        ]);
    }

    private function present(LearningPath $path): array
    {
        return [
            'id' => $path->getKey(),
            'title' => $path->title,
            'description' => $path->description,
            'enrollments' => $path->enrollments_count ?? null,
            'steps' => $path->steps->map(fn ($s) => ['course_id' => $s->course_id, 'position' => $s->position, 'prerequisites' => $s->prerequisiteIds()])->values(),
        ];
    }
}
