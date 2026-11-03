<?php

namespace Ulams\CourseVariants\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\CourseVariants\Exceptions\VariantException;
use Ulams\CourseVariants\Services\AssignmentService;
use Ulams\CourseVariants\Services\FamilyAccess;
use Ulams\CourseVariants\Services\PlacementService;

/**
 * The optional placement check for learners (ADR 0192). Advisory only: the result suggests a variant with its reason and
 * the learner confirms it or picks another. Scored by code from cited quiz questions that already exist; nothing about
 * the learner is sent to a model. Needs access to the family.
 *
 * @OA\Get(path="/api/courses/{course}/placement", operationId="courses.by-course.placement.get", summary="The placement questions of a course's family (no answer key); 404 when the check is off or not ready", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="questions: id, type, stem, options (id, text); cited quiz questions chosen for the levels of the family", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="No access to the family"), @OA\Response(response=404, description="No placement check for this course"))
 * @OA\Post(path="/api/courses/{course}/placement/attempts", operationId="courses.by-course.placement.attempts.post", summary="Score a placement attempt by code and get the suggested variant with the reason (advisory)", tags={"Course Variants"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"answers"},
 *         @OA\Property(property="answers", type="object", description="Question id to the option ids chosen", additionalProperties=@OA\Schema(type="array", @OA\Items(type="string")))))),
 *     @OA\Response(response=201, description="id, scores per level, suggestedCourseId, reason; the answers are not stored", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="No access to the family"), @OA\Response(response=404, description="No placement check for this course"))
 */
class PlacementController extends VariantApiController
{
    public function __construct(private readonly PlacementService $placement, private readonly AssignmentService $assignments, private readonly FamilyAccess $access)
    {
    }

    public function show(Request $request, int $course): JsonResponse
    {
        $group = $this->ready($request, $course);

        return $this->ok(['questions' => $this->placement->questions($group)]);
    }

    public function attempt(Request $request, int $course): JsonResponse
    {
        $group = $this->ready($request, $course);
        $data = $request->validate(['answers' => ['required', 'array', 'max:50'], 'answers.*' => ['array', 'max:12'], 'answers.*.*' => ['string', 'max:40']]);
        $userId = (int) $request->user()->getKey();
        $attempt = $this->placement->attempt($userId, $group, $data['answers'], $this->assignments->effectiveCourseId($userId, $group));

        return $this->ok(VariantController::presentAttempt($attempt), 201);
    }

    private function ready(Request $request, int $course)
    {
        $group = $this->groupOf($course)->load('members');
        if (!$this->placement->available($group)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('There is no placement check for this course.');
        }
        if (!$this->access->covers((int) $request->user()->getKey(), $group)) {
            throw VariantException::notEnrolled();
        }

        return $group;
    }
}
