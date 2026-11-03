<?php

namespace Ulams\CourseVariants\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Courses\Models\Course;
use Ulams\CourseVariants\Enums\CourseVariantsPermissionsEnum;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Services\AssignmentService;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\CourseVariants\Services\Levels;

abstract class VariantApiController extends Controller
{
    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK'], $status);
    }

    /** The family a course belongs to, as its default course or as a variant; 404 when it has none. */
    protected function groupOf(int $courseId): VariantGroup
    {
        return app(CourseVariantServiceContract::class)->groupForCourse($courseId) ?? throw new NotFoundHttpException('This course has no variants.');
    }

    protected function guardAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->can(CourseVariantsPermissionsEnum::ASSIGN, 'api'), 403);
    }

    protected function adminGroup(Request $request, string $group): VariantGroup
    {
        $this->guardAdmin($request);

        return VariantGroup::query()->with('members')->findOrFail($group);
    }

    /**
     * What the picker shows: the courses a learner can pick, each with its name, level, role and a line on who it is for.
     * Public data only (the course page is public too).
     *
     * @return array<int,array{courseId:int,title:string,label:string,level:?string,role:?string,isDefault:bool,description:?string,duration:?string}>
     */
    protected function pickerItems(VariantGroup $group): array
    {
        $selectable = app(AssignmentService::class)->selectableCourseIds($group);
        $courses = Course::query()->whereIn('id', $selectable)->get()->keyBy('id');
        $items = [];
        foreach (Levels::all($group) as $row) {
            $course = $courses->get($row['courseId']);
            if ($course === null) {
                continue;
            }
            $member = $row['position'] >= 0 ? $group->members->firstWhere('course_id', $row['courseId']) : null;
            $items[] = [
                'courseId' => $row['courseId'],
                'title' => (string) $course->title,
                'label' => $member?->label ?? ($group->default_label ?: (string) $course->title),
                'level' => $row['level'],
                'role' => $row['role'],
                'isDefault' => $row['position'] < 0,
                'description' => $this->describe($course),
                'duration' => $course->duration,
            ];
        }

        return $items;
    }

    private function describe(Course $course): ?string
    {
        $text = trim((string) ($course->target_group ?: $course->summary ?: $course->subtitle));

        return $text === '' ? null : mb_substr($text, 0, 240);
    }
}
