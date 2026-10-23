<?php

namespace Ulams\CourseVariants\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Ulams\CourseVariants\Models\Rule;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Services\RuleAssigner;

/**
 * Assignment rules of a family (the `variant` action of the enrolment rules, ADR 0192): "a learner whose attributes match
 * all conditions is put in this variant". Rules route learners who already have access and have no variant yet; they never
 * enrol anyone and never override a learner's own choice or an admin's assignment. Permission `course_variants_assign`.
 *
 * @OA\Get(path="/api/admin/course-variant-groups/{group}/rules", operationId="admin.course-variant-groups.by-group.rules.get", summary="List the assignment rules of a family", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="rules in the order they are tried", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 * @OA\Post(path="/api/admin/course-variant-groups/{group}/rules", operationId="admin.course-variant-groups.by-group.rules.post", summary="Create an assignment rule", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"name","course_id","conditions"},
 *         @OA\Property(property="name", type="string"), @OA\Property(property="course_id", type="integer", description="The variant course it assigns"), @OA\Property(property="active", type="boolean"), @OA\Property(property="position", type="integer"),
 *         @OA\Property(property="conditions", type="array", description="All must hold", @OA\Items(type="object",
 *             @OA\Property(property="attribute", type="string", example="department"),
 *             @OA\Property(property="operator", type="string", enum={"equals","not_equals","in","contains","starts_with","ends_with","exists"}),
 *             @OA\Property(property="value", type="array", @OA\Items(type="string"))))))),
 *     @OA\Response(response=201, description="the rule", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=422, description="Validation error"))
 * @OA\Put(path="/api/admin/course-variant-groups/{group}/rules/{rule}", operationId="admin.course-variant-groups.by-group.rules.by-rule.put", summary="Update an assignment rule", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="rule", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the rule", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown rule"))
 * @OA\Delete(path="/api/admin/course-variant-groups/{group}/rules/{rule}", operationId="admin.course-variant-groups.by-group.rules.by-rule.delete", summary="Delete an assignment rule (learners already assigned stay)", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="rule", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="deleted", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown rule"))
 * @OA\Post(path="/api/admin/course-variant-groups/{group}/rules/{rule}/evaluate", operationId="admin.course-variant-groups.by-group.rules.by-rule.evaluate.post", summary="Preview or run a rule now", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="rule", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\MediaType(mediaType="application/json", @OA\Schema(type="object", @OA\Property(property="dry_run", type="boolean", default=true)))),
 *     @OA\Response(response=200, description="count and a sample of the learners the rule would assign; assigned count when dry_run is false", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 */
class RuleAdminController extends VariantApiController
{
    public const OPERATORS = ['equals', 'not_equals', 'in', 'contains', 'starts_with', 'ends_with', 'exists'];

    public function __construct(private readonly RuleAssigner $assigner)
    {
    }

    public function index(Request $request, string $group): JsonResponse
    {
        $g = $this->adminGroup($request, $group);

        return $this->ok(Rule::query()->where('group_id', $g->id)->orderBy('position')->orderBy('id')->get()->map(fn (Rule $r) => $this->present($r))->values());
    }

    public function store(Request $request, string $group): JsonResponse
    {
        $g = $this->adminGroup($request, $group);
        $data = $this->validated($request, $g, true);

        return $this->ok($this->present(Rule::query()->create($data + ['group_id' => $g->id])), 201);
    }

    public function update(Request $request, string $group, int $rule): JsonResponse
    {
        $g = $this->adminGroup($request, $group);
        $row = Rule::query()->where('group_id', $g->id)->findOrFail($rule);
        $row->fill($this->validated($request, $g, false))->save();

        return $this->ok($this->present($row));
    }

    public function destroy(Request $request, string $group, int $rule): JsonResponse
    {
        $g = $this->adminGroup($request, $group);
        Rule::query()->where('group_id', $g->id)->findOrFail($rule)->delete();

        return $this->ok(null);
    }

    public function evaluate(Request $request, string $group, int $rule): JsonResponse
    {
        $g = $this->adminGroup($request, $group);
        $row = Rule::query()->where('group_id', $g->id)->findOrFail($rule);
        $preview = $this->assigner->preview($g, $row);
        if ($request->boolean('dry_run', true)) {
            return $this->ok($preview);
        }

        return $this->ok(['matched' => $preview['count'], 'assigned' => $this->assigner->evaluateAll($g, $row)]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, VariantGroup $group, bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';
        $data = $request->validate([
            'name' => [$presence, 'string', 'max:255'],
            'course_id' => [$presence, 'integer'],
            'active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'conditions' => [$presence, 'array', 'min:1', 'max:20'],
            'conditions.*.attribute' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'conditions.*.operator' => ['required', ValidationRule::in(self::OPERATORS)],
            'conditions.*.value' => ['sometimes', 'array', 'max:50'],
            'conditions.*.value.*' => ['string', 'max:255'],
        ]);
        if (isset($data['course_id']) && !in_array((int) $data['course_id'], $group->courseIds(), true)) {
            abort(422, 'The course must be a variant of this family.');
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function present(Rule $r): array
    {
        return ['id' => (int) $r->id, 'name' => $r->name, 'courseId' => (int) $r->course_id, 'active' => $r->active, 'position' => (int) $r->position, 'conditions' => $r->conditions];
    }
}
