<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Models\Rule;
use Ulams\Compliance\Services\RuleEngine;

/**
 * Enrolment rules: "a user with these attributes is enrolled in this path, due in N days". Permission
 * `compliance_manage`. Attributes: `group` (group names), a user column or a custom user field (model-fields).
 * Rules run when a user is saved and in the daily `compliance:process`.
 *
 * @OA\Get(path="/api/admin/compliance/rules", operationId="admin.compliance.rules.get", summary="List enrolment rules", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="rules", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/compliance/rules", operationId="admin.compliance.rules.post", summary="Create an enrolment rule", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"name","path_id","conditions"},
 *         @OA\Property(property="name", type="string"), @OA\Property(property="path_id", type="integer"), @OA\Property(property="active", type="boolean"),
 *         @OA\Property(property="due_days", type="integer", nullable=true),
 *         @OA\Property(property="conditions", type="array", description="All must hold", @OA\Items(type="object",
 *             @OA\Property(property="attribute", type="string", example="department"),
 *             @OA\Property(property="operator", type="string", enum={"equals","not_equals","in","contains","starts_with","ends_with","exists"}),
 *             @OA\Property(property="value", type="array", @OA\Items(type="string"))))))),
 *     @OA\Response(response=201, description="the rule", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=422, description="Validation error"))
 * @OA\Put(path="/api/admin/compliance/rules/{id}", operationId="admin.compliance.rules.by-id.put", summary="Update an enrolment rule", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the rule", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown rule"))
 * @OA\Delete(path="/api/admin/compliance/rules/{id}", operationId="admin.compliance.rules.by-id.delete", summary="Delete an enrolment rule (existing enrolments stay)", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="deleted", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown rule"))
 * @OA\Post(path="/api/admin/compliance/rules/{id}/evaluate", operationId="admin.compliance.rules.by-id.evaluate.post", summary="Preview or run a rule now", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\MediaType(mediaType="application/json", @OA\Schema(type="object", @OA\Property(property="dry_run", type="boolean", default=true)))),
 *     @OA\Response(response=200, description="count and a sample of the users that match and are not enrolled yet; enrolled count when dry_run is false", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 */
class RuleController extends ComplianceController
{
    public function __construct(private RuleEngine $engine)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);

        return $this->ok(Rule::query()->orderBy('name')->get()->map(fn (Rule $r) => $this->present($r))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);

        return $this->ok($this->present(Rule::query()->create($this->validated($request, true))), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $rule = Rule::query()->findOrFail($id);
        $rule->fill($this->validated($request, false))->save();

        return $this->ok($this->present($rule));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        Rule::query()->findOrFail($id)->delete();

        return $this->ok(null);
    }

    public function evaluate(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $rule = Rule::query()->with('path')->findOrFail($id);
        $preview = $this->engine->preview($rule);
        if ($request->boolean('dry_run', true)) {
            return $this->ok($preview);
        }

        $enrolled = 0;
        $model = config('auth.providers.users.model');
        $model::query()->orderBy('id')->chunkById(200, function ($users) use ($rule, &$enrolled) {
            foreach ($users as $user) {
                $enrolled += $this->engine->evaluateUser($user, $rule);
            }
        });

        return $this->ok(['matched' => $preview['count'], 'enrolled' => $enrolled]);
    }

    private function validated(Request $request, bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$presence, 'string', 'max:255'],
            'path_id' => [$presence, 'integer', 'exists:compliance_paths,id'],
            'active' => ['sometimes', 'boolean'],
            'due_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'conditions' => [$presence, 'array', 'min:1', 'max:20'],
            'conditions.*.attribute' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'conditions.*.operator' => ['required', ValidationRule::in(RuleEngine::OPERATORS)],
            'conditions.*.value' => ['sometimes', 'array', 'max:50'],
            'conditions.*.value.*' => ['string', 'max:255'],
        ]);
    }

    private function present(Rule $rule): array
    {
        return [
            'id' => $rule->getKey(), 'name' => $rule->name, 'active' => $rule->active, 'path_id' => $rule->path_id,
            'due_days' => $rule->due_days, 'conditions' => $rule->conditions,
        ];
    }
}
