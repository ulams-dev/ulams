<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Models\Competency;
use Ulams\Compliance\Models\CourseCompetency;
use Ulams\Compliance\Services\CompetencySuggester;
use Ulams\Courses\Models\Course;

/**
 * Competency tags of a course. AI suggests, the author confirms: suggestions are `suggested` and count for
 * nothing (no skill profile, no rule) until confirmed. Allowed for `compliance_manage`, or for
 * `compliance_competencies` on a course the user authors.
 *
 * @OA\Get(path="/api/admin/compliance/courses/{course}/competencies", operationId="admin.compliance.courses.by-course.competencies.get", summary="Competency tags of a course (confirmed and suggested)", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="tags with status, source, rationale and the lessons they point at", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Put(path="/api/admin/compliance/courses/{course}/competencies/{competency}", operationId="admin.compliance.courses.by-course.competencies.by-competency.put", summary="Tag a course with a competency (confirmed by the author), or confirm a suggestion", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="competency", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the confirmed tag", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown course or competency"))
 * @OA\Delete(path="/api/admin/compliance/courses/{course}/competencies/{competency}", operationId="admin.compliance.courses.by-course.competencies.by-competency.delete", summary="Remove a tag or reject a suggestion", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="competency", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="removed", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/compliance/courses/{course}/competencies/suggest", operationId="admin.compliance.courses.by-course.competencies.suggest.post", summary="Ask the AI which competencies the course teaches", tags={"Compliance"}, security={{"passport": {}}},
 *     description="One AI call (logged with model, tokens and cost). Replaces the pending suggestions; confirmed tags are untouched. Needs a competency framework and at least one lesson.",
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the new suggestions, for the author to confirm", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=503, description="AI is not configured or the call failed"))
 */
class CourseCompetencyController extends ComplianceController
{
    public function __construct(private CompetencySuggester $suggester)
    {
    }

    public function index(Request $request, int $course): JsonResponse
    {
        $course = $this->authorizedCourse($request, $course);

        return $this->ok($this->tags($course));
    }

    public function confirm(Request $request, int $course, int $competency): JsonResponse
    {
        $course = $this->authorizedCourse($request, $course);
        $competency = Competency::query()->findOrFail($competency);

        $tag = CourseCompetency::query()->firstOrNew(['course_id' => $course->getKey(), 'competency_id' => $competency->getKey()]);
        $tag->fill(['status' => CourseCompetency::CONFIRMED, 'source' => $tag->exists ? $tag->source : 'author', 'confirmed_by' => $request->user()->getKey(), 'confirmed_at' => now()])->save();

        return $this->ok($this->present($tag->load('competency')));
    }

    public function destroy(Request $request, int $course, int $competency): JsonResponse
    {
        $course = $this->authorizedCourse($request, $course);
        CourseCompetency::query()->where('course_id', $course->getKey())->where('competency_id', $competency)->delete();

        return $this->ok(null);
    }

    public function suggest(Request $request, int $course): JsonResponse
    {
        $course = $this->authorizedCourse($request, $course);

        try {
            $this->suggester->suggest($course, $request->user());
        } catch (LlmException $e) {
            return response()->json(['success' => false, 'message' => 'The AI could not suggest competencies: ' . $e->getMessage()], 503);
        }

        return $this->ok($this->tags($course));
    }

    private function authorizedCourse(Request $request, int $courseId): Course
    {
        $user = $request->user();
        $course = Course::query()->findOrFail($courseId);
        $allowed = $user?->can(CompliancePermissionsEnum::MANAGE, 'api')
            || ($user?->can(CompliancePermissionsEnum::COMPETENCIES, 'api') && $course->authors()->whereKey($user->getKey())->exists());
        abort_unless($allowed, 403);

        return $course;
    }

    /** @return list<array<string, mixed>> */
    private function tags(Course $course): array
    {
        return CourseCompetency::query()->with('competency')->where('course_id', $course->getKey())->orderBy('status')->orderBy('id')->get()
            ->map(fn (CourseCompetency $tag) => $this->present($tag))->values()->all();
    }

    private function present(CourseCompetency $tag): array
    {
        return [
            'competency_id' => $tag->competency_id,
            'name' => $tag->competency?->name,
            'status' => $tag->status,
            'source' => $tag->source,
            'rationale' => $tag->rationale,
            'lesson_ids' => $tag->lesson_ids ?? [],
            'confirmed_at' => $tag->confirmed_at?->toIso8601String(),
        ];
    }
}
