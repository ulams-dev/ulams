<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Http\AssignmentPresenter;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Services\AssignmentService;
use Ulams\Compliance\Services\ManagerService;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;

/**
 * Required training. Admin endpoints need `compliance_manage`; `my-assignments` is the learner's own list
 * and `team/assignments` lists the direct reports of the manager that is logged in.
 *
 * @OA\Get(path="/api/admin/compliance/assignments", operationId="admin.compliance.assignments.get", summary="List required-training assignments", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"assigned","overdue","completed","cancelled"})),
 *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="course_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=25)),
 *     @OA\Response(response=200, description="assignments with user, course and certificate", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/compliance/assignments", operationId="admin.compliance.assignments.post", summary="Assign a required course to users", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"course_id","user_ids"},
 *         @OA\Property(property="course_id", type="integer"),
 *         @OA\Property(property="user_ids", type="array", @OA\Items(type="integer")),
 *         @OA\Property(property="due_at", type="string", format="date-time", nullable=true)))),
 *     @OA\Response(response=201, description="one assignment per user (existing open ones are returned unchanged)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Validation error"))
 * @OA\Delete(path="/api/admin/compliance/assignments/{id}", operationId="admin.compliance.assignments.by-id.delete", summary="Cancel an open assignment", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="cancelled assignment", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown assignment"))
 * @OA\Get(path="/api/compliance/my-assignments", operationId="compliance.my-assignments.get", summary="My required training", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="the logged-in user's assignments, newest first", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 * @OA\Get(path="/api/compliance/team/assignments", operationId="compliance.team.assignments.get", summary="Required training of my direct reports", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"assigned","overdue","completed","cancelled"})),
 *     @OA\Response(response=200, description="assignments of the users that have the logged-in user as manager (empty for everyone else)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 */
class AssignmentController extends ComplianceController
{
    public function __construct(private AssignmentService $assignments, private ManagerService $managers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $query = Assignment::query()->with(['user', 'course', 'certificate'])->latest('id');
        $this->filter($query, $request);
        foreach (['user_id', 'course_id'] as $key) {
            if ($request->filled($key)) {
                $query->where($key, (int) $request->input($key));
            }
        }

        return $this->page($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);
        $course = Course::query()->findOrFail($data['course_id']);
        $due = isset($data['due_at']) ? Carbon::parse($data['due_at']) : null;

        $created = User::query()->whereIn('id', $data['user_ids'])->get()
            ->map(fn (User $user) => $this->assignments->assign($user, $course, $due, 'manual', $request->user()))
            ->each->load(['user', 'course', 'certificate']);

        return $this->ok($created->map(fn (Assignment $a) => AssignmentPresenter::toArray($a))->values(), 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $assignment = $this->assignments->cancel(Assignment::query()->findOrFail($id))->load(['user', 'course', 'certificate']);

        return $this->ok(AssignmentPresenter::toArray($assignment));
    }

    public function mine(Request $request): JsonResponse
    {
        $query = Assignment::query()->with(['course', 'certificate'])->where('user_id', $request->user()->getKey())->latest('id');

        return $this->ok($query->get()->map(fn (Assignment $a) => AssignmentPresenter::toArray($a))->values());
    }

    public function team(Request $request): JsonResponse
    {
        $query = Assignment::query()->with(['user', 'course', 'certificate'])
            ->whereIn('user_id', $this->managers->reportIds($request->user()))
            ->latest('id');
        $this->filter($query, $request);

        return $this->page($query->paginate($this->perPage($request)));
    }

    private function filter(Builder $query, Request $request): void
    {
        match ($request->input('status')) {
            'overdue' => $query->open()->where('due_at', '<', now()),
            AssignmentStatus::ASSIGNED => $query->open()->where(fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now())),
            AssignmentStatus::COMPLETED, AssignmentStatus::CANCELLED => $query->where('status', $request->input('status')),
            default => null,
        };
    }

    private function perPage(Request $request): int
    {
        return max(1, min(200, (int) $request->input('per_page', 25)));
    }

    private function page(LengthAwarePaginator $page): JsonResponse
    {
        return $this->ok(
            collect($page->items())->map(fn (Assignment $a) => AssignmentPresenter::toArray($a))->values(),
            200,
            ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        );
    }
}
