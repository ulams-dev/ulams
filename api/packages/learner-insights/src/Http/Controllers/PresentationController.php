<?php

namespace Ulams\LearnerInsights\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Http\Controllers\Swagger\PresentationSwagger;
use Ulams\LearnerInsights\Services\PresentationProfiles;

class PresentationController extends Controller implements PresentationSwagger
{
    public function __construct(private readonly PresentationProfiles $profiles)
    {
    }

    public function show(Request $request, int $course): JsonResponse
    {
        $this->authorizeAttend($request, $course);

        return response()->json(['data' => $this->profiles->forLearner((int) $request->user()->getKey(), $course)]);
    }

    public function update(Request $request, int $course): JsonResponse
    {
        $this->authorizeAttend($request, $course);
        $changes = $request->validate([
            'density' => ['sometimes', 'nullable', Rule::in(PresentationProfiles::OPTIONS['density'])],
            'chunking' => ['sometimes', 'nullable', Rule::in(PresentationProfiles::OPTIONS['chunking'])],
            'navigation' => ['sometimes', 'nullable', Rule::in(PresentationProfiles::OPTIONS['navigation'])],
            'hints' => ['sometimes', 'nullable', 'integer', Rule::in(PresentationProfiles::OPTIONS['hints'])],
        ]);
        $userId = (int) $request->user()->getKey();
        $this->profiles->override($userId, $course, $changes);

        return response()->json(['data' => $this->profiles->forLearner($userId, $course)]);
    }

    private function authorizeAttend(Request $request, int $course): void
    {
        $model = Course::query()->findOrFail($course);
        abort_unless(Gate::forUser($request->user())->allows('attend', $model), 403);
    }
}
