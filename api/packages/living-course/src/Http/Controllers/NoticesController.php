<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\ElementStatus;
use Ulams\LivingCourse\Models\LearnerNotice;
use Ulams\LivingCourse\Models\Proposal;

/**
 * Learner side (ADR 0033): the caller's own notices about updated courses, and the opt-in "an
 * update is under review" marker. Notices never change progress.
 *
 * @OA\Get(path="/api/living-course/courses/{course}/notices", summary="The caller's open update notices for a course", tags={"Living Course learner"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="notices"), @OA\Response(response=401, description="not signed in"))
 * @OA\Post(path="/api/living-course/notices/{notice}/dismiss", summary="Mark a notice as reviewed", tags={"Living Course learner"}, security={{"passport": {}}},
 *     @OA\Parameter(name="notice", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="dismissed"), @OA\Response(response=404, description="not the caller's notice"))
 * @OA\Get(path="/api/living-course/courses/{course}/freshness", summary="Topics with an update under review (only when the course shows it)", tags={"Living Course learner"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="topics"), @OA\Response(response=404, description="unknown course"))
 */
class NoticesController extends Controller
{
    private static function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK']);
    }

    public function index(Request $request, int $course): JsonResponse
    {
        $notices = LearnerNotice::query()->where('user_id', $request->user()->getKey())->where('course_id', $course)->where('status', 'open')->orderBy('id')->get();
        $topics = Topic::query()->whereIn('id', $notices->pluck('topic_id'))->pluck('title', 'id');

        return self::ok($notices->map(fn (LearnerNotice $n) => [
            'id' => $n->id,
            'kind' => $n->kind,
            'topicId' => $n->topic_id,
            'topicTitle' => $topics[$n->topic_id] ?? null,
            'giftQuestionId' => $n->gift_question_id ?: null,
            'message' => $n->message,
            'createdAt' => $n->created_at?->toIso8601String(),
        ])->all());
    }

    public function dismiss(Request $request, int $notice): JsonResponse
    {
        $n = LearnerNotice::query()->where('user_id', $request->user()->getKey())->find($notice);
        if ($n === null) {
            throw new NotFoundHttpException('Notice not found.');
        }
        // a re-attempt notice closes when the quiz is retaken, not by reading it
        if ($n->kind !== 'question_reattempt' && $n->status === 'open') {
            $n->forceFill(['status' => 'dismissed', 'resolved_at' => now()])->save();
        }

        return self::ok(['id' => $n->id, 'status' => $n->status]);
    }

    public function freshness(Request $request, int $course): JsonResponse
    {
        $c = Course::query()->find($course);
        if ($c === null || !$c->users()->where('users.id', $request->user()->getKey())->exists()) {
            throw new NotFoundHttpException('Course not found.');
        }
        $session = Session::withTrashed()->where('course_id', $c->getKey())->first();
        $connections = $session ? Connection::query()->where('session_id', $session->id)->get() : collect();
        if ($session === null || !$connections->contains(fn (Connection $x) => (bool) $x->setting('show_pending_to_learners', false))) {
            return self::ok(['topics' => []]);
        }
        $after = (int) config('living_course.learner_notice_after_days', 3);
        $pending = ElementStatus::query()->where('session_id', $session->id)->whereIn('status', ['pending', 'source_removed'])->where('since', '<=', now()->subDays($after))->get();
        $proposal = Proposal::query()->where('session_id', $session->id)->whereIn('status', Proposal::OPEN)->latest('created_at')->first();
        if ($pending->isEmpty() || $proposal === null) {
            return self::ok(['topics' => []]);
        }
        $map = \Ulams\CourseBuilder\Models\EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'topic')->pluck('entity_id', 'element_id');
        $doc = $session->currentVersion?->document ?? [];
        $topics = [];
        foreach ($pending as $element) {
            $found = \Ulams\CourseBuilder\Blueprint\Blueprint::find($doc, $element->element_id);
            $lessonId = $found['lessonId'] ?? null;
            $topicId = $lessonId !== null ? ($map[$lessonId] ?? null) : null;
            if ($topicId !== null && !isset($topics[$topicId])) {
                // never the element, its reason or the source text: only that an update is under review
                $topics[$topicId] = ['topicId' => (int) $topicId, 'since' => $element->since?->toIso8601String()];
            }
        }

        return self::ok(['topics' => array_values($topics)]);
    }
}
