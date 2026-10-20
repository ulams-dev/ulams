<?php

namespace Ulams\Tutor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Ulams\Courses\Models\Course;
use Ulams\Tutor\Http\Controllers\Swagger\TutorSwagger;
use Ulams\Tutor\Http\Requests\AskRequest;
use Ulams\Tutor\Models\Conversation;
use Ulams\Tutor\Services\TutorException;
use Ulams\Tutor\Services\TutorService;

/** The learner's tutor. Conversations are owned by the learner: another learner's id is a 404. */
class TutorController extends Controller implements TutorSwagger
{
    public function __construct(private readonly TutorService $tutor)
    {
    }

    public function ask(AskRequest $request, int $course): JsonResponse
    {
        $model = Course::query()->findOrFail($course);
        try {
            $result = $this->tutor->ask($request->user(), $model, (string) $request->input('text'), $request->integer('topicId') ?: null, $request->input('conversationId') ?: null);
        } catch (TutorException $e) {
            return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason], $e->status);
        }

        return response()->json(['data' => $result + ['label' => 'AI tutor · answers only from this course']]);
    }

    public function conversations(Request $request, int $course): JsonResponse
    {
        $rows = Conversation::query()->where('user_id', (int) $request->user()->getKey())->where('course_id', $course)->orderByDesc('last_message_at')->limit(50)->get();

        return response()->json(['data' => $rows->map(fn (Conversation $c) => ['id' => $c->id, 'lastMessageAt' => $c->last_message_at->toIso8601String(), 'expiresAt' => $c->expires_at->toIso8601String()])->all()]);
    }

    public function show(Request $request, string $conversation): JsonResponse
    {
        $c = $this->owned($request, $conversation);

        return response()->json(['data' => [
            'id' => $c->id, 'courseId' => (int) $c->course_id, 'expiresAt' => $c->expires_at->toIso8601String(),
            'messages' => $c->messages->map(fn ($m) => ['id' => $m->id, 'role' => $m->role, 'text' => $m->text, 'citations' => $m->citations ?? [], 'refusal' => $m->refusal, 'at' => $m->created_at->toIso8601String()])->all(),
        ]]);
    }

    public function destroy(Request $request, string $conversation): JsonResponse
    {
        $c = $this->owned($request, $conversation);
        DB::transaction(function () use ($c) {
            DB::table('tutor_messages')->where('conversation_id', $c->id)->delete();
            $c->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function analytics(Request $request, int $course): JsonResponse
    {
        $model = Course::query()->findOrFail($course);
        $user = $request->user();
        abort_unless($user && ($user->can('learner_insights_view', 'api') || $model->hasAuthor($user)), 403);

        $min = max(1, (int) config('learner_insights.min_group_size', 10));
        $learners = (int) DB::table('tutor_conversations')->where('course_id', $course)->distinct()->count('user_id');
        if ($learners < $min) {
            return response()->json(['data' => ['courseId' => $course, 'minGroupSize' => $min, 'smallGroup' => true]]);
        }
        $answers = DB::table('tutor_messages as m')->join('tutor_conversations as c', 'c.id', '=', 'm.conversation_id')->where('c.course_id', $course)->where('m.role', 'tutor')
            ->selectRaw("COUNT(*) as questions, SUM(CASE WHEN m.refusal = 'out_of_scope' THEN 1 ELSE 0 END) as out_of_scope, SUM(CASE WHEN m.refusal = 'attempt_active' THEN 1 ELSE 0 END) as attempt_refusals")->first();
        $questions = (int) $answers->questions;

        return response()->json(['data' => [
            'courseId' => $course, 'minGroupSize' => $min, 'smallGroup' => false, 'learners' => $learners, 'questions' => $questions,
            'outOfScopeShare' => $questions ? round(((int) $answers->out_of_scope) / $questions, 4) : null,
            'refusalsDuringAttempts' => (int) $answers->attempt_refusals,
        ]]);
    }

    private function owned(Request $request, string $id): Conversation
    {
        return Conversation::query()->with('messages')->where('user_id', (int) $request->user()->getKey())->findOrFail($id);
    }
}
