<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Models\CourseSetting;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\Courses\Models\Course;

/**
 * Compliance policy of a course. Permission `compliance_manage`.
 *
 * @OA\Get(path="/api/admin/compliance/courses/{course}/settings", operationId="admin.compliance.courses.by-course.settings.get", summary="Compliance settings of a course (defaults when none are saved)", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="mandatory, validity_days, recertify_before_days, default_due_days, reminder_days, escalation_days", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown course"))
 * @OA\Put(path="/api/admin/compliance/courses/{course}/settings", operationId="admin.compliance.courses.by-course.settings.put", summary="Save the compliance settings of a course", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object",
 *         @OA\Property(property="mandatory", type="boolean"),
 *         @OA\Property(property="validity_days", type="integer", nullable=true, description="Days a new certificate stays valid; null = never expires"),
 *         @OA\Property(property="recertify_before_days", type="integer", nullable=true, description="Start recertification this many days before expiry; 0 = off"),
 *         @OA\Property(property="default_due_days", type="integer", nullable=true, description="Due date offset for assignments without an explicit date"),
 *         @OA\Property(property="reminder_days", type="array", nullable=true, @OA\Items(type="integer")),
 *         @OA\Property(property="escalation_days", type="array", nullable=true, @OA\Items(type="integer"))))),
 *     @OA\Response(response=200, description="saved settings", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Validation error"))
 */
class CourseSettingsController extends ComplianceController
{
    public function __construct(private CourseSettings $settings)
    {
    }

    public function show(Request $request, int $course): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        Course::query()->findOrFail($course);

        return $this->ok($this->present($this->settings->for($course)));
    }

    public function update(Request $request, int $course): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::MANAGE);
        Course::query()->findOrFail($course);

        $values = $request->validate([
            'mandatory' => ['sometimes', 'boolean'],
            'validity_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'recertify_before_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3650'],
            'default_due_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'reminder_days' => ['sometimes', 'nullable', 'array', 'max:10'],
            'reminder_days.*' => ['integer', 'min:0', 'max:3650'],
            'escalation_days' => ['sometimes', 'nullable', 'array', 'max:10'],
            'escalation_days.*' => ['integer', 'min:0', 'max:3650'],
        ]);
        foreach (['reminder_days', 'escalation_days'] as $key) {
            if (isset($values[$key])) {
                $values[$key] = CourseSetting::days($values[$key], $key === 'reminder_days');
            }
        }

        return $this->ok($this->present($this->settings->save($course, $values)));
    }

    private function present(CourseSetting $setting): array
    {
        return [
            'course_id' => $setting->course_id,
            'mandatory' => (bool) $setting->mandatory,
            'validity_days' => $setting->validity_days,
            'recertify_before_days' => $setting->recertify_before_days,
            'default_due_days' => $setting->default_due_days,
            'reminder_days' => $setting->reminderDays(),
            'escalation_days' => $setting->escalationDays(),
        ];
    }
}
