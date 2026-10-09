<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Services\Contracts\ProgressServiceContract;
use Ulams\Courses\Tests\Models\User;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\LearnerNotice;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Services\ProgressRules;
use Ulams\LivingCourse\Tests\TestCase;
use Ulams\TopicTypeGift\Dtos\QuizAttemptDto;
use Ulams\TopicTypeGift\Events\QuizAttemptFinishedEvent;
use Ulams\TopicTypeGift\Exceptions\TooManyAttemptsException;
use Ulams\TopicTypeGift\Models\AttemptAnswer;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;

/**
 * ADR 0033, the regression of the whole pipeline: a learner completes lessons and a quiz, the
 * author accepts an update with a minor change, a major change, a corrected answer, a removed
 * question, a removed lesson and an added lesson, and nothing the learner earned changes.
 */
class ProgressSurvivesUpdateTest extends TestCase
{
    private function lessonId(Session $session, string $number): string
    {
        foreach (Blueprint::lessons($session->currentVersion->document) as $item) {
            if ($item['number'] === $number) {
                return $item['lesson']['id'];
            }
        }
        $this->fail("No lesson {$number}");
    }

    private function entity(Session $session, string $element, string $type): int
    {
        return (int) EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $element)->where('entity_type', $type)->value('entity_id');
    }

    /** @return array<string,string> a checksum per table that holds learner results */
    private function checksums(): array
    {
        $sum = fn (string $table, array $columns) => md5((string) json_encode(DB::table($table)->orderBy('id')->get($columns)->all()));

        return [
            'answers' => $sum('topic_gift_attempt_answers', ['id', 'topic_gift_quiz_attempt_id', 'topic_gift_question_id', 'answer', 'score', 'feedback']),
            'attempts' => $sum('topic_gift_quiz_attempts', ['id', 'topic_gift_quiz_id', 'user_id', 'started_at', 'end_at', 'max_score']),
            'progress' => $sum('course_progress', ['id', 'user_id', 'topic_id', 'status', 'finished_at']),
        ];
    }

    private function answered(QuizAttempt $attempt, GiftQuestion $question, float $score): void
    {
        AttemptAnswer::factory()->create(['topic_gift_quiz_attempt_id' => $attempt->getKey(), 'topic_gift_question_id' => $question->getKey(), 'answer' => ['x'], 'score' => $score, 'graded_at' => now()]);
    }

    public function testNothingALearnerEarnedChangesWhenAnUpdateIsApplied(): void
    {
        Queue::fake([\Ulams\TopicTypeGift\Jobs\MarkAttemptAsEnded::class]);
        $author = $this->author();
        $session = $this->toApplied($author);
        $courseId = (int) $session->course_id;
        $doc = $session->currentVersion->document;
        $lesson21 = $this->lessonId($session, '2.1');
        $lesson22 = $this->lessonId($session, '2.2');
        $lesson42 = $this->lessonId($session, '4.2');

        $learner = User::factory()->create();
        $started = User::factory()->create();
        $bystander = User::factory()->create();
        foreach ([$learner, $started, $bystander] as $u) {
            $u->courses()->syncWithoutDetaching([$courseId]);
        }

        // quizzes of lessons 2.1, 2.2 and 4.2: 5 points per question, the learner scored 4 of 5 twice (8 of 10) in 2.1
        $quizOf = function (string $lessonId) use ($session, $doc) {
            foreach (Blueprint::lessons($doc) as $item) {
                if ($item['lesson']['id'] === $lessonId) {
                    $topic = Topic::query()->findOrFail($this->entity($session, $item['lesson']['quiz']['id'], 'quiz_topic'));

                    return [GiftQuiz::query()->findOrFail($topic->topicable_id), $item['lesson']['quiz']['questions'], $topic];
                }
            }
            $this->fail('no quiz');
        };
        [$quiz21, $questions21, $quizTopic21] = $quizOf($lesson21);
        [$quiz22, $questions22] = $quizOf($lesson22);
        [$quiz42, , $quizTopic42] = $quizOf($lesson42);
        foreach ([$quiz21, $quiz22, $quiz42] as $quiz) {
            GiftQuestion::query()->where('topic_gift_quiz_id', $quiz->getKey())->update(['score' => 5]);
            $quiz->forceFill(['max_attempts' => 1, 'min_pass_score' => 6])->save();
        }
        $attempt21 = QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz21->getKey(), 'user_id' => $learner->getKey(), 'started_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(10), 'max_score' => 10]);
        foreach (GiftQuestion::query()->where('topic_gift_quiz_id', $quiz21->getKey())->get() as $q) {
            $this->answered($attempt21, $q, 4);
        }
        $attempt22 = QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz22->getKey(), 'user_id' => $learner->getKey(), 'started_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(10), 'max_score' => 10]);
        foreach (GiftQuestion::query()->where('topic_gift_quiz_id', $quiz22->getKey())->get() as $q) {
            $this->answered($attempt22, $q, 5);
        }
        $attempt42 = QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz42->getKey(), 'user_id' => $learner->getKey(), 'started_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(10), 'max_score' => 10]);
        foreach (GiftQuestion::query()->where('topic_gift_quiz_id', $quiz42->getKey())->get() as $q) {
            $this->answered($attempt42, $q, 5);
        }

        // the learner finished the whole course; another learner started lesson 2.1
        $topicIds = Topic::query()->whereIn('lesson_id', \Ulams\Courses\Models\Lesson::query()->where('course_id', $courseId)->select('id'))->where('active', true)->pluck('id');
        foreach ($topicIds as $topicId) {
            CourseProgress::create(['user_id' => $learner->getKey(), 'topic_id' => $topicId, 'status' => ProgressStatus::COMPLETE, 'finished_at' => now()->subDay()]);
        }
        $learner->courses()->updateExistingPivot($courseId, ['finished' => true]);
        $topic21 = $this->entity($session, $lesson21, 'topic');
        $topic42 = $this->entity($session, $lesson42, 'topic');
        CourseProgress::create(['user_id' => $started->getKey(), 'topic_id' => $topic21, 'status' => ProgressStatus::IN_PROGRESS]);

        $percentBefore = $attempt21->refresh()->result_percent;
        $this->assertSame(80.0, $percentBefore);
        $before = $this->checksums();

        // the update: source v2
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        // one minor change and one removed question in lesson 2.2 (the other changes are major and a corrected answer)
        $blocks22 = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->where('element_type', 'block')->where('label', 'like', 'Lesson 2.2%')->orderBy('id')->get();
        $minor = $blocks22->last();
        $minor->forceFill(['change_class' => 'minor', 'severity' => 'minor', 'after' => array_merge($minor->before, ['markdown' => $minor->before['markdown'] . ' (reviewed)'])])->save();
        $questions22Items = ProposalItem::query()->where('proposal_id', $proposal->id)->where('element_type', 'question')->where('label', 'like', 'Lesson 2.2%')->orderBy('id')->get();
        $removedQuestion = $questions22Items->last();
        $removedQuestion->forceFill(['kind' => 'remove', 'change_class' => 'removed', 'after' => null, 'answer_status' => null])->save();
        $removedEntity = $this->entity($session, $removedQuestion->element_id, 'gift_question');
        $this->assertGreaterThan(0, AttemptAnswer::query()->where('topic_gift_question_id', $removedEntity)->count(), 'the question has answers');

        // the learner impact is shown before the author decides
        $detail = $this->actingAs($author, 'api')->getJson("/api/admin/living-course/proposals/{$proposal->id}")->assertOk()->json('data.learnerImpact');
        $this->assertSame(2, $detail['learners']['topic_updated'], 'the finisher and the learner who started 2.1');
        $this->assertSame(1, $detail['learners']['question_reattempt']);
        $this->actingAs($author, 'api')->putJson("/api/admin/living-course/proposals/{$proposal->id}/learner-note", ['note' => 'The brew ratio is 1:15 now.'])->assertOk();

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);
        $this->assertSame('applied', $proposal->refresh()->status);

        // 1. nothing the learner earned changed
        $after = $this->checksums();
        $this->assertSame($before['answers'], $after['answers'], 'no answer row was changed, deleted or regraded');
        $this->assertSame($before['attempts'], $after['attempts'], 'no attempt was changed');
        $this->assertSame($before['progress'], $after['progress'], 'no progress row was changed or removed');
        $attempt21->refresh();
        $this->assertSame(8.0, (float) $attempt21->result_score);
        $this->assertSame(10.0, (float) $attempt21->max_score);
        $this->assertSame($percentBefore, $attempt21->result_percent);
        $this->assertTrue($attempt21->is_passed);

        // 2. removed elements are retired, not deleted
        $removedTopic = Topic::query()->findOrFail($topic42);
        $this->assertFalse((bool) $removedTopic->active);
        $this->assertNotNull(EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lesson42)->where('entity_type', 'topic')->value('retired_at'));
        $this->assertFalse((bool) $quizTopic42->refresh()->active);
        $this->assertNotNull(GiftQuestion::query()->findOrFail($removedEntity)->archived_at, 'the removed question is archived');
        $this->assertGreaterThan(0, AttemptAnswer::query()->where('topic_gift_question_id', $removedEntity)->count(), 'and keeps its answers');
        $this->assertSame(10.0, (float) $attempt22->refresh()->max_score, 'the snapshot protects the attempt that used the removed question');
        $this->assertSame(100.0, $attempt22->result_percent);

        // 3. the corrected question is offered again; the unchanged question and the other learners get nothing
        $notices = LearnerNotice::query()->where('proposal_id', $proposal->id)->get();
        $reattempt = $notices->where('kind', 'question_reattempt');
        $this->assertCount(3, $reattempt, 'two corrected questions of quiz 2.1 and the corrected question left in quiz 2.2');
        $this->assertSame([$learner->getKey()], $reattempt->pluck('user_id')->unique()->values()->all());
        $this->assertSame(2, $reattempt->where('topic_id', $quizTopic21->getKey())->count());
        $this->assertSame(ProgressRules::REATTEMPT_MESSAGE, $reattempt->first()->message);
        $updated = $notices->where('kind', 'topic_updated');
        $this->assertEqualsCanonicalizing([$learner->getKey(), $started->getKey()], $updated->where('topic_id', $topic21)->pluck('user_id')->all());
        $this->assertSame('The brew ratio is 1:15 now.', $updated->first()->message);
        $this->assertSame(0, $notices->where('user_id', $bystander->getKey())->count());
        $this->assertSame($learner->getKey(), $notices->where('kind', 'topic_retired')->where('topic_id', $topic42)->first()->user_id);

        // 4. one extra attempt for the corrected quiz, then the notice closes and the limit is back
        $service = app(QuizAttemptServiceContract::class);
        $retake = $service->getActive(new QuizAttemptDto($quiz21->getKey(), $learner->getKey(), Carbon::now()));
        $this->assertNotSame($attempt21->getKey(), $retake->getKey());
        $this->assertSame(10, (int) $retake->getRawOriginal('max_score'));
        $retake->forceFill(['end_at' => now()->subMinute()])->save();
        event(new QuizAttemptFinishedEvent($learner, $retake->refresh()));
        $this->assertSame(['done', 'done'], $reattempt->where('topic_id', $quizTopic21->getKey())->map(fn ($n) => $n->refresh()->status)->values()->all());
        $this->assertSame('open', $reattempt->where('topic_id', '!=', $quizTopic21->getKey())->first()->refresh()->status, 'quiz 2.2 was not retaken yet');
        try {
            $service->getActive(new QuizAttemptDto($quiz21->getKey(), $learner->getKey(), Carbon::now()));
            $this->fail('A second extra attempt was granted.');
        } catch (TooManyAttemptsException) {
            $this->assertTrue(true);
        }
        $this->assertSame($before['answers'], $this->checksums()['answers'], 'retaking adds an attempt, it never rewrites the old one');

        // 5. a lesson added later: the learner who finished keeps the course finished
        $current = $session->refresh()->currentVersion->document;
        $new = $current['modules'][0]['lessons'][0];
        $new['id'] = Blueprint::newId();
        $new['title'] = 'Storing beans';
        foreach ($new['objectives'] as $i => $o) {
            $new['objectives'][$i]['id'] = Blueprint::newId();
        }
        foreach ($new['blocks'] as $i => $b) {
            $new['blocks'][$i]['id'] = Blueprint::newId();
            $new['blocks'][$i]['objectiveIds'] = [$new['objectives'][0]['id']];
        }
        $new['quiz'] = null;
        $current['modules'][0]['lessons'][] = $new;
        $versions = app(VersionService::class);
        $version = $versions->create($session, $current, 'update', 'ai', Version::APPROVED, $session->currentVersion, 'added', null, [], $author->getKey());
        $versions->setCurrent($session, $version);
        app(BlueprintApplier::class)->apply($session, $version, $author, true, $proposal->id);
        $rules = app(ProgressRules::class);
        $created = $rules->run($proposal);
        $this->assertSame(1, $created['course_extended']);
        $this->assertSame(0, array_sum($rules->run($proposal)), 'running the rules again creates nothing twice');
        $this->assertSame($learner->getKey(), LearnerNotice::query()->where('kind', 'course_extended')->firstOrFail()->user_id);

        $anyTopic = Topic::query()->findOrFail($topic21);
        app(ProgressServiceContract::class)->ping($learner, $anyTopic);
        $this->assertTrue($learner->refresh()->finishedCourse($courseId), 'adding a lesson does not revoke the finished course');
        $completed = CourseProgress::query()->where('user_id', $learner->getKey())->where('status', ProgressStatus::COMPLETE)->count();
        $this->assertSame($topicIds->count(), $completed, 'completed rows are untouched');

        $audit = AuditEntry::query()->where('action', 'progress.rules_applied')->where('subject_id', $proposal->id)->first();
        $this->assertNotNull($audit);
        $this->assertTrue(app(\Ulams\LivingCourse\Services\AuditLog::class)->verify()['ok']);
    }
}
