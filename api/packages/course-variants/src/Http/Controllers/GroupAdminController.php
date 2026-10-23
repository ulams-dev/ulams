<?php

namespace Ulams\CourseVariants\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Support\GroupTree;
use Ulams\Courses\Models\Course;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Models\PlacementItem;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Services\AssignmentService;
use Ulams\CourseVariants\Services\Levels;
use Ulams\CourseVariants\Services\PlacementService;

/**
 * Families of course variants for admins (ADR 0192, permission `course_variants_assign`): the family's settings
 * (lock assigned variants, pin the course mandatory training counts, placement check, hints), who is in which variant, an
 * assignment of learners or groups, and the placement questions.
 *
 * @OA\Get(path="/api/admin/course-variant-groups", operationId="admin.course-variant-groups.get", summary="List the families of course variants, optionally the one a course belongs to", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course_id", in="query", @OA\Schema(type="integer")), @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="groups with settings and counts", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Get(path="/api/admin/course-variant-groups/{group}", operationId="admin.course-variant-groups.by-group.get", summary="One family: settings, variant courses, where learners are and the placement check", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the family", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown family"))
 * @OA\Put(path="/api/admin/course-variant-groups/{group}", operationId="admin.course-variant-groups.by-group.put", summary="Change the settings of a family", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object",
 *         @OA\Property(property="pinned_course_id", type="integer", nullable=true, description="Mandatory training counts only this course of the family; null lets any variant count"),
 *         @OA\Property(property="lock_assigned", type="boolean", description="A variant assigned by an admin or a rule cannot be changed by the learner"),
 *         @OA\Property(property="placement_enabled", type="boolean"), @OA\Property(property="hints_enabled", type="boolean")))),
 *     @OA\Response(response=200, description="the family", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=422, description="The pinned course is not in the family"))
 * @OA\Get(path="/api/admin/course-variant-groups/{group}/assignments", operationId="admin.course-variant-groups.by-group.assignments.get", summary="Who is in which variant (current assignments, with the source of each)", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")), @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="assignments and per-variant counts", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 * @OA\Put(path="/api/admin/course-variant-groups/{group}/assignments", operationId="admin.course-variant-groups.by-group.assignments.put", summary="Assign learners or user groups to a variant; the learner sees it as assigned by their organisation", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"course_id"},
 *         @OA\Property(property="course_id", type="integer"), @OA\Property(property="user_ids", type="array", @OA\Items(type="integer")), @OA\Property(property="group_ids", type="array", description="User groups; their members (and subgroups) are assigned", @OA\Items(type="integer"))))),
 *     @OA\Response(response=200, description="assigned (learners whose variant changed) and matched", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=422, description="Not a variant of this family, or nobody selected"))
 * @OA\Get(path="/api/admin/course-variant-groups/{group}/placement", operationId="admin.course-variant-groups.by-group.placement.get", summary="The placement questions with their answer key and citations", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="enabled, available, levels and items", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 * @OA\Post(path="/api/admin/course-variant-groups/{group}/placement/rebuild", operationId="admin.course-variant-groups.by-group.placement.rebuild.post", summary="Choose the placement questions again from the family's cited quiz questions (no model call)", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="items and levels counts; zero items when the family has too few suitable questions", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 */
class GroupAdminController extends VariantApiController
{
    public function __construct(private readonly AssignmentService $assignments, private readonly PlacementService $placement)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->guardAdmin($request);
        $query = VariantGroup::query()->with('members')->orderBy('id');
        if ($request->filled('course_id')) {
            $courseId = (int) $request->query('course_id');
            $query->where(fn ($q) => $q->where('default_course_id', $courseId)->orWhereIn('id', DB::table('course_variant_group_members')->where('course_id', $courseId)->select('group_id')));
        }
        $page = $query->paginate(25);
        $titles = Course::query()->whereIn('id', $page->getCollection()->pluck('default_course_id'))->pluck('title', 'id');

        return $this->ok([
            'groups' => $page->getCollection()->map(fn (VariantGroup $g) => $this->present($g, $titles[$g->default_course_id] ?? null))->all(),
            'total' => $page->total(), 'page' => $page->currentPage(), 'perPage' => $page->perPage(),
        ]);
    }

    public function show(Request $request, string $group): JsonResponse
    {
        $g = $this->adminGroup($request, $group);
        $titles = Course::query()->whereIn('id', $g->courseIds())->pluck('title', 'id');

        return $this->ok($this->present($g, $titles[$g->default_course_id] ?? null) + [
            'courses' => collect($g->courseIds())->map(fn (int $id) => ['courseId' => $id, 'title' => $titles[$id] ?? null] + Levels::of($g, $id) + [
                'label' => $id === (int) $g->default_course_id ? $g->default_label : $g->members->firstWhere('course_id', $id)?->label,
                'isDefault' => $id === (int) $g->default_course_id,
                'retired' => (bool) $g->members->firstWhere('course_id', $id)?->retired_at,
            ])->all(),
            'learners' => $this->assignments->summary($g),
            'placement' => $this->placementState($g),
        ]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $g = $this->adminGroup($request, $group);
        $data = $request->validate([
            'pinned_course_id' => ['sometimes', 'nullable', 'integer'],
            'lock_assigned' => ['sometimes', 'boolean'],
            'placement_enabled' => ['sometimes', 'boolean'],
            'hints_enabled' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['pinned_course_id']) && !in_array((int) $data['pinned_course_id'], $g->courseIds(), true)) {
            abort(422, 'The pinned course must be a course of this family.');
        }
        $g->forceFill($data)->save();

        return $this->show($request, $group);
    }

    public function assignments(Request $request, string $group): JsonResponse
    {
        $g = $this->adminGroup($request, $group);
        $query = Assignment::query()->current()->where('group_id', $g->id)->orderByDesc('decided_at')->orderByDesc('id');
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }
        $page = $query->paginate(50);
        $model = config('auth.providers.users.model');
        $names = $model::query()->whereIn('id', $page->getCollection()->pluck('user_id'))->get()->mapWithKeys(fn ($u) => [$u->getKey() => trim((string) ($u->name ?? $u->email ?? ''))])->all();

        return $this->ok([
            'assignments' => $page->getCollection()->map(fn (Assignment $a) => [
                'userId' => (int) $a->user_id, 'userName' => $names[$a->user_id] ?? '#' . $a->user_id, 'courseId' => (int) $a->course_id, 'source' => $a->source,
                'reason' => $a->reason, 'decidedBy' => $a->decided_by, 'decidedAt' => $a->decided_at->toIso8601String(),
            ])->all(),
            'summary' => $this->assignments->summary($g),
            'total' => $page->total(), 'page' => $page->currentPage(), 'perPage' => $page->perPage(),
        ]);
    }

    public function assign(Request $request, string $group): JsonResponse
    {
        $g = $this->adminGroup($request, $group);
        $data = $request->validate([
            'course_id' => ['required', 'integer'],
            'user_ids' => ['sometimes', 'array', 'max:500'], 'user_ids.*' => ['integer'],
            'group_ids' => ['sometimes', 'array', 'max:50'], 'group_ids.*' => ['integer'],
        ]);
        $userIds = array_map('intval', $data['user_ids'] ?? []);
        if (!empty($data['group_ids'])) {
            $groups = array_map('intval', $data['group_ids']);
            $userIds = array_merge($userIds, DB::table('group_user')->whereIn('group_id', array_merge($groups, GroupTree::descendantsOfMany($groups)))->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        }
        $userIds = array_slice(array_values(array_unique($userIds)), 0, 1000);
        if ($userIds === []) {
            abort(422, 'Select at least one learner or group.');
        }
        $model = config('auth.providers.users.model');
        $existing = $model::query()->whereIn('id', $userIds)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $this->ok(['matched' => count($existing), 'assigned' => $this->assignments->assignMany($g, (int) $data['course_id'], $existing)]);
    }

    public function placementShow(Request $request, string $group): JsonResponse
    {
        return $this->ok($this->placementState($this->adminGroup($request, $group)));
    }

    public function placementRebuild(Request $request, string $group): JsonResponse
    {
        $g = $this->adminGroup($request, $group);

        return $this->ok($this->placement->rebuild($g) + ['available' => $this->placement->available($g->refresh()->load('members'))]);
    }

    /** @return array<string,mixed> */
    private function placementState(VariantGroup $g): array
    {
        return [
            'enabled' => (bool) $g->placement_enabled,
            'available' => $this->placement->available($g),
            'levels' => $this->placement->levels($g),
            'minItems' => PlacementService::MIN_ITEMS,
            'items' => PlacementItem::query()->where('group_id', $g->id)->orderBy('position')->get()->map(fn (PlacementItem $i) => [
                'id' => (int) $i->id, 'level' => $i->level, 'courseId' => (int) $i->source_course_id, 'elementId' => $i->element_id, 'question' => $i->question,
            ])->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function present(VariantGroup $g, ?string $title): array
    {
        return [
            'id' => $g->id, 'defaultCourseId' => (int) $g->default_course_id, 'defaultTitle' => $title,
            'defaultLabel' => $g->default_label, 'defaultLevel' => $g->default_level, 'defaultRole' => $g->default_role,
            'variants' => $g->members->count() + 1,
            'pinnedCourseId' => $g->pinned_course_id, 'lockAssigned' => (bool) $g->lock_assigned,
            'placementEnabled' => (bool) $g->placement_enabled, 'hintsEnabled' => (bool) $g->hints_enabled,
        ];
    }
}
