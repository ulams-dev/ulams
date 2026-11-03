<?php

namespace Ulams\LearnerInsights\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Enums\LearnerInsightsPermissionsEnum;
use Ulams\LearnerInsights\Http\Controllers\Swagger\SettingsSwagger;
use Ulams\LearnerInsights\Http\Requests\CourseSettingsRequest;
use Ulams\LearnerInsights\Models\CourseSettings;
use Ulams\LearnerInsights\Services\ContextBuilder;

class SettingsController extends Controller implements SettingsSwagger
{
    public function __construct(private readonly ContextBuilder $contexts)
    {
    }

    public function tenant(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json(['data' => ['rules' => config('learner_insights.rules')]]);
    }

    public function showCourse(Request $request, int $course): JsonResponse
    {
        $this->authorizeManage($request);
        Course::query()->findOrFail($course);

        return $this->payload($course);
    }

    public function updateCourse(CourseSettingsRequest $request, int $course): JsonResponse
    {
        Course::query()->findOrFail($course);
        $rules = array_filter((array) $request->validated('rules'), fn ($params) => $params !== []);

        CourseSettings::query()->updateOrCreate(['course_id' => $course], ['rules' => $rules, 'updated_by' => $request->user()->getKey()]);

        return $this->payload($course);
    }

    private function payload(int $course): JsonResponse
    {
        return response()->json(['data' => [
            'overrides' => CourseSettings::query()->find($course)?->rules ?? (object) [],
            'effective' => $this->contexts->thresholds($course)->all(),
        ]]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(LearnerInsightsPermissionsEnum::LEARNER_INSIGHTS_MANAGE, 'api'), 403);
    }
}
