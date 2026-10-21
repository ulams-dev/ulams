<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Models\Competency;
use Ulams\Compliance\Services\CompetencyService;

/**
 * The competency framework: a tree of competencies (areas and skills). Reading needs `compliance_manage` or
 * `compliance_competencies` (authors pick from it); changing it needs `compliance_manage`.
 *
 * @OA\Get(path="/api/admin/compliance/competencies", summary="The competency framework as a tree", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="competencies with nested children"), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/compliance/competencies", summary="Add a competency", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"name"},
 *         @OA\Property(property="name", type="string"), @OA\Property(property="code", type="string", nullable=true),
 *         @OA\Property(property="description", type="string", nullable=true), @OA\Property(property="parent_id", type="integer", nullable=true)))),
 *     @OA\Response(response=201, description="the competency"), @OA\Response(response=422, description="Validation error"))
 * @OA\Put(path="/api/admin/compliance/competencies/{id}", summary="Rename or move a competency", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the competency"), @OA\Response(response=422, description="Moving it under itself or its descendants"))
 * @OA\Delete(path="/api/admin/compliance/competencies/{id}", summary="Delete a competency (its sub-competencies move up, course tags go)", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="deleted"), @OA\Response(response=404, description="Unknown competency"))
 */
class CompetencyController extends ComplianceController
{
    public function __construct(private CompetencyService $competencies)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can(CompliancePermissionsEnum::MANAGE, 'api') || $user?->can(CompliancePermissionsEnum::COMPETENCIES, 'api'), 403);

        return $this->ok($this->competencies->tree());
    }

    public function store(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);

        return $this->ok($this->competencies->present($this->competencies->save($this->validated($request, true))), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $competency = Competency::query()->findOrFail($id);

        return $this->ok($this->competencies->present($this->competencies->save($this->validated($request, false, $id), $competency)));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        $competency = Competency::query()->findOrFail($id);
        Competency::query()->where('parent_id', $competency->getKey())->update(['parent_id' => $competency->parent_id]);
        $competency->delete();

        return $this->ok(null);
    }

    private function validated(Request $request, bool $required, ?int $id = null): array
    {
        return $request->validate([
            'name' => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:64', 'unique:compliance_competencies,code' . ($id ? ",{$id}" : '')],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:compliance_competencies,id'],
        ]);
    }
}
