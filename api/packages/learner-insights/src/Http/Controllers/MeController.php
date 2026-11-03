<?php

namespace Ulams\LearnerInsights\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\LearnerInsights\Http\Controllers\Swagger\MeSwagger;
use Ulams\LearnerInsights\Http\Requests\MySettingsRequest;
use Ulams\LearnerInsights\Models\LearnerStatus;
use Ulams\LearnerInsights\Scoring\Reason;
use Ulams\LearnerInsights\Services\Privacy;

/** The learner's own view and controls. Every query is scoped to the authenticated user. */
class MeController extends Controller implements MeSwagger
{
    public function __construct(private readonly Privacy $privacy)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $statuses = LearnerStatus::query()->where('user_id', $userId)->orderBy('course_id')->orderBy('element_key')->get();
        $titles = DB::table('courses')->whereIn('id', $statuses->pluck('course_id')->unique())->pluck('title', 'id');

        $courses = $statuses->groupBy('course_id')->map(function ($rows, $courseId) use ($titles) {
            $course = $rows->firstWhere('element_key', '');

            return [
                'courseId' => (int) $courseId,
                'title' => $titles[$courseId] ?? null,
                'status' => $course?->status ?? 'on_track',
                'reasons' => $course ? $this->reasons($course) : [],
                'since' => $course?->since?->toIso8601String(),
                'elements' => $rows->where('element_key', '!=', '')->map(fn ($r) => ['elementKey' => $r->element_key, 'status' => $r->status, 'reasons' => $this->reasons($r)])->values()->all(),
            ];
        })->values()->all();

        return response()->json(['data' => [
            'enabled' => (bool) config('learner_insights.enabled'),
            'settings' => $this->privacy->settings($userId),
            'requiresConsent' => (bool) config('learner_insights.require_consent'),
            'courses' => $courses,
            'retention' => ['signalsDays' => (int) config('learner_insights.retention.signals_days'), 'historyDays' => (int) config('learner_insights.retention.history_days')],
        ]]);
    }

    public function updateSettings(MySettingsRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $this->privacy->update($userId, $request->safe()->only(['enabled', 'help', 'nudges', 'consent']));
        if ($request->boolean('eraseExisting') && $request->has('enabled') && !$request->boolean('enabled')) {
            $this->privacy->erase($userId);
        }

        return response()->json(['data' => $this->privacy->settings($userId)]);
    }

    public function export(Request $request): StreamedResponse
    {
        $userId = (int) $request->user()->getKey();
        $payload = ['exportedAt' => now()->toIso8601String(), 'userId' => $userId] + $this->privacy->export($userId);

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, 'learner-insights-export.json', ['Content-Type' => 'application/json']);
    }

    public function erase(Request $request): JsonResponse
    {
        return response()->json(['data' => ['deleted' => $this->privacy->erase((int) $request->user()->getKey())]]);
    }

    /** @return array<int,array{rule:string,message:string}> */
    private function reasons(LearnerStatus $row): array
    {
        return array_map(fn (array $r) => ['rule' => $r['rule'], 'message' => Reason::fromArray($r)->message()], $row->reasons);
    }
}
