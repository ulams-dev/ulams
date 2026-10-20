<?php

namespace Ulams\LearnerInsights\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Http\Controllers\Swagger\SupportSwagger;
use Ulams\LearnerInsights\Http\Requests\FeedbackRequest;
use Ulams\LearnerInsights\Http\Requests\ReflectionRequest;
use Ulams\LearnerInsights\Models\Remediation;
use Ulams\LearnerInsights\Models\RemediationAssignment;
use Ulams\LearnerInsights\Remediation\ReflectionService;
use Ulams\LearnerInsights\Remediation\RemediationService;
use Ulams\LearnerInsights\Scoring\Reason;
use Ulams\LearnerInsights\Services\Eligibility;
use Ulams\LearnerInsights\Services\Privacy;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;

/** The learner's extra help. Every query is scoped to the authenticated learner. */
class SupportController extends Controller implements SupportSwagger
{
    public function __construct(private readonly Eligibility $eligibility, private readonly Privacy $privacy, private readonly SignalRecorder $signals)
    {
    }

    public function index(Request $request, int $course, int $topic): JsonResponse
    {
        $user = $request->user();
        $topicModel = Topic::query()->with('lesson')->find($topic);
        if ($topicModel === null || (int) $topicModel->lesson?->course_id !== $course) {
            return response()->json(['message' => 'Not found'], 404);
        }
        if (!Gate::forUser($user)->allows('attend', $topicModel->lesson)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $userId = (int) $user->getKey();
        $enabled = $this->eligibility->allowed($userId) && (bool) config('learner_insights.remediation.enabled') && $this->privacy->settings($userId)['help'];
        if (!$enabled) {
            return response()->json(['data' => ['enabled' => false, 'items' => []]]);
        }

        app(RemediationService::class)->retireOutdated();
        $assignments = RemediationAssignment::query()->with('remediation')
            ->where('user_id', $userId)->whereNull('hidden_at')
            ->whereHas('remediation', fn ($q) => $q->where('topic_id', $topic)->where('course_id', $course)->whereIn('status', [Remediation::GENERATING, Remediation::READY]))
            ->orderBy('created_at')->get();

        $items = [];
        foreach ($assignments as $a) {
            $items[] = $this->item($a, $userId);
        }

        return response()->json(['data' => ['enabled' => true, 'items' => $items]]);
    }

    public function feedback(FeedbackRequest $request, string $assignment): JsonResponse
    {
        $a = $this->owned($request, $assignment);
        $helpful = $request->boolean('helpful');
        $a->update(['feedback' => $helpful ? 'helpful' : 'not_helpful']);
        $this->signals->queue(new SignalData((int) $a->user_id, SignalType::REMEDIATION_FEEDBACK, 'remediation', 'feedback:' . $a->id . ':' . ($helpful ? 'y' : 'n'), CarbonImmutable::now(), (int) $a->remediation->course_id, $a->remediation->topic_id, null, null, ['remediation_id' => $a->remediation_id, 'helpful' => $helpful]));

        return response()->json(['data' => ['feedback' => $a->feedback]]);
    }

    public function hide(Request $request, string $assignment): JsonResponse
    {
        $this->owned($request, $assignment)->update(['hidden_at' => CarbonImmutable::now()]);

        return response()->json(['data' => ['hidden' => true]]);
    }

    public function reflection(ReflectionRequest $request, string $assignment): JsonResponse
    {
        $a = $this->owned($request, $assignment);
        if (!app(LlmClient::class)->enabled()) {
            return response()->json(['message' => 'AI is disabled'], 503);
        }
        $service = app(ReflectionService::class);
        try {
            $result = $service->feedback($a, (string) $request->input('text'));
        } catch (LlmException) {
            return response()->json(['message' => 'The feedback could not be produced. Try again later.'], 503);
        }
        if ($result === null) {
            return response()->json(['message' => 'Daily limit reached'], 429);
        }

        return response()->json(['data' => $result + ['remaining' => $service->remaining((int) $a->user_id)]]);
    }

    private function owned(Request $request, string $id): RemediationAssignment
    {
        return RemediationAssignment::query()->with('remediation')->where('user_id', (int) $request->user()->getKey())->findOrFail($id);
    }

    /** @return array<string,mixed> */
    private function item(RemediationAssignment $a, int $userId): array
    {
        $r = $a->remediation;
        $reasons = array_map(fn (array $x) => Reason::fromArray($x)->message(), $a->reasons);
        $base = ['assignmentId' => $a->id, 'kind' => $r->kind, 'status' => $r->status, 'reasons' => $reasons, 'why' => $reasons[0] ?? null, 'feedback' => $a->feedback];
        if ($r->status !== Remediation::READY) {
            return $base + ['document' => null, 'citations' => []];
        }
        if ($a->shown_at === null) {
            $a->update(['shown_at' => CarbonImmutable::now()]);
            $this->signals->queue(new SignalData($userId, SignalType::REMEDIATION_SHOWN, 'remediation', 'shown:' . $a->id, CarbonImmutable::now(), (int) $r->course_id, $r->topic_id, null, null, ['remediation_id' => $r->id]));
        }
        $fragments = Fragment::query()->whereIn('id', (array) $r->citations)->get()->keyBy('id');

        return $base + [
            'document' => $r->document,
            'citations' => array_map(fn ($id) => ['fragmentId' => $id, 'label' => $fragments[$id]?->label() ?? $id], (array) $r->citations),
            'aiLabel' => "AI-generated, based on this course's sources",
        ];
    }
}
