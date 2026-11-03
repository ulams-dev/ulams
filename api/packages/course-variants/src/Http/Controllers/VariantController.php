<?php

namespace Ulams\CourseVariants\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Ulams\CourseVariants\Exceptions\VariantException;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Models\Hint;
use Ulams\CourseVariants\Models\PlacementAttempt;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Services\AssignmentService;
use Ulams\CourseVariants\Services\HintService;
use Ulams\CourseVariants\Services\PlacementService;

/**
 * Variants of a course for learners (ADR 0192, CV-5): the picker's data (public, like the course page), the learner's own
 * variant and the choice to change it, and the hint to switch. `{course}` is the default course or any variant of the
 * family. The LMS owns who may open what; choosing needs access to the family.
 *
 * @OA\Get(path="/api/courses/{course}/variants", operationId="courses.by-course.variants.get", summary="The variants of a course's family for the picker (public data), or null when it has none", tags={"Course Variants"},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="groupId, defaultCourseId and variants (courseId, title, label, level, role, isDefault, description, duration); data is null for a course without variants", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 * @OA\Get(path="/api/courses/{course}/variant", operationId="courses.by-course.variant.get", summary="The signed-in learner's variant of this course's family, whether they may change it, the placement check and a hint to switch", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="variants, current (courseId, source, reason, byOrganisation), effectiveCourseId, enrolled, canChange, lockedReason, placement, hint", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="The course has no variants"))
 * @OA\Put(path="/api/courses/{course}/variant", operationId="courses.by-course.variant.put", summary="Choose or switch variant: enrols the learner in the variant's course through the family entitlement and carries over identical completed topics", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"course_id"},
 *         @OA\Property(property="course_id", type="integer", description="The variant course to move to"),
 *         @OA\Property(property="source", type="string", enum={"self","placement","hint"}, default="self"),
 *         @OA\Property(property="attempt_id", type="string", description="With source placement: the placement attempt whose suggestion is confirmed"),
 *         @OA\Property(property="hint_id", type="integer", description="With source hint: the hint that is accepted")))),
 *     @OA\Response(response=200, description="the state as in GET, plus changed and carried (topics completed by carry-over)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="No access to the family, or the variant was assigned by the organisation and the family is locked"), @OA\Response(response=422, description="Not a variant of this course, not available, or the placement attempt or hint does not match"))
 * @OA\Post(path="/api/courses/{course}/variant/hints/{hint}/dismiss", operationId="courses.by-course.variant.hints.by-hint.dismiss.post", summary="Dismiss a hint to switch variant; hints stop for this course", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")), @OA\Parameter(name="hint", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the state as in GET", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown hint"))
 */
class VariantController extends VariantApiController
{
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly PlacementService $placement,
        private readonly HintService $hints,
    ) {
    }

    public function variants(int $course): JsonResponse
    {
        $group = app(\Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract::class)->groupForCourse($course);
        if ($group === null) {
            return $this->ok(null);
        }
        $group->load('members');

        return $this->ok(['groupId' => $group->id, 'defaultCourseId' => (int) $group->default_course_id, 'variants' => $this->pickerItems($group)]);
    }

    public function show(Request $request, int $course): JsonResponse
    {
        return $this->ok($this->state((int) $request->user()->getKey(), $this->groupOf($course)->load('members')));
    }

    public function choose(Request $request, int $course): JsonResponse
    {
        $group = $this->groupOf($course)->load('members');
        $userId = (int) $request->user()->getKey();
        $data = $request->validate([
            'course_id' => ['required', 'integer'],
            'source' => ['sometimes', ValidationRule::in([Assignment::SELF, Assignment::PLACEMENT, Assignment::HINT])],
            'attempt_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'hint_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        $target = (int) $data['course_id'];
        $source = $data['source'] ?? Assignment::SELF;
        $reason = null;
        $hintId = null;
        if ($source === Assignment::PLACEMENT) {
            $attempt = PlacementAttempt::query()->where('user_id', $userId)->where('group_id', $group->id)->find((string) ($data['attempt_id'] ?? ''));
            if ($attempt === null || (int) $attempt->suggested_course_id !== $target) {
                throw VariantException::invalid('That placement result does not suggest this variant.');
            }
            $reason = $attempt->reason;
        } elseif ($source === Assignment::HINT) {
            $hint = Hint::query()->where('user_id', $userId)->where('group_id', $group->id)->where('status', Hint::SHOWN)->find((int) ($data['hint_id'] ?? 0));
            if ($hint === null || (int) $hint->suggested_course_id !== $target) {
                throw VariantException::invalid('That hint does not suggest this variant.');
            }
            $reason = ['key' => 'hint', 'params' => ['direction' => $hint->direction, 'reasons' => $hint->reasons]];
            $hintId = (int) $hint->id;
        }

        $result = $this->assignments->choose($userId, $group, $target, $source, $reason, $userId, null, $hintId);

        return $this->ok($this->state($userId, $group) + ['changed' => $result['changed'], 'carried' => $result['carried']]);
    }

    public function dismissHint(Request $request, int $course, int $hint): JsonResponse
    {
        $group = $this->groupOf($course)->load('members');
        $userId = (int) $request->user()->getKey();
        $row = Hint::query()->where('user_id', $userId)->where('group_id', $group->id)->findOrFail($hint);
        $this->hints->dismiss($row);

        return $this->ok($this->state($userId, $group));
    }

    /** @return array<string,mixed> */
    private function state(int $userId, VariantGroup $group): array
    {
        $current = $this->assignments->current($userId, $group);
        $may = $this->assignments->mayChange($userId, $group);
        $attempt = PlacementAttempt::query()->where('user_id', $userId)->where('group_id', $group->id)->latest('created_at')->first();
        $hint = $this->hints->forLearner($userId, $group);

        return [
            'groupId' => $group->id,
            'defaultCourseId' => (int) $group->default_course_id,
            'variants' => $this->pickerItems($group),
            'current' => $current === null ? null : [
                'courseId' => (int) $current->course_id, 'source' => $current->source, 'reason' => $current->reason,
                'byOrganisation' => $current->byOrganisation(), 'decidedAt' => $current->decided_at->toIso8601String(),
            ],
            'effectiveCourseId' => $this->assignments->effectiveCourseId($userId, $group),
            'enrolled' => $may['reason'] !== 'not_enrolled',
            'canChange' => $may['allowed'],
            'lockedReason' => $may['reason'],
            'placement' => [
                'enabled' => (bool) $group->placement_enabled,
                'available' => $this->placement->available($group),
                'lastAttempt' => $attempt === null ? null : $this->presentAttempt($attempt),
            ],
            'hint' => $hint === null ? null : [
                'id' => (int) $hint->id, 'direction' => $hint->direction, 'courseId' => (int) $hint->course_id,
                'suggestedCourseId' => (int) $hint->suggested_course_id, 'reasons' => $hint->reasons,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function presentAttempt(PlacementAttempt $attempt): array
    {
        return [
            'id' => $attempt->id, 'scores' => $attempt->scores, 'suggestedCourseId' => $attempt->suggested_course_id,
            'reason' => $attempt->reason, 'createdAt' => $attempt->created_at->toIso8601String(),
        ];
    }
}
