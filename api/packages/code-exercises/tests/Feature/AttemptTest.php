<?php

namespace Ulams\CodeExercises\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ulams\CodeExercises\Events\CodeAttemptFinished;
use Ulams\CodeExercises\Models\CodeExerciseAttempt;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\CodeExercises\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;

/**
 * The learner side: opening an exercise, reporting a run, progress and the learner signal. Results come from
 * the learner's browser, so they are recomputed, bounded and stored as unverified practice attempts.
 */
class AttemptTest extends TestCase
{
    use CreatesUsers;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_uploads.content_origin' => 'http://coffee.content.localhost']);
        $this->course = Course::factory()->create(['status' => 'published']);
    }

    private function topic(array $content = []): Topic
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $topic->topicable()->associate(CodeExerciseTopic::query()->create($content + $this->exercise()))->save();

        return $topic->refresh();
    }

    private function student()
    {
        $student = $this->makeStudent();
        $this->course->users()->attach($student->getKey());

        return $student;
    }

    /** @return array<int, array{name: string, status: string, message?: string}> */
    private function results(int $passed, int $failed = 0): array
    {
        $rows = [];
        for ($i = 1; $i <= $passed; $i++) {
            $rows[] = ['name' => "passes {$i}", 'status' => 'pass'];
        }
        for ($i = 1; $i <= $failed; $i++) {
            $rows[] = ['name' => "fails {$i}", 'status' => 'fail', 'message' => 'Expected 3, received 0'];
        }

        return $rows;
    }

    private function report($student, Topic $topic, array $body)
    {
        return $this->actingAs($student, 'api')->postJson("/api/code-exercises/topics/{$topic->getKey()}/attempts", $body);
    }

    private function progressStatus(Topic $topic, $student): ?int
    {
        return $topic->progress()->where('user_id', $student->getKey())->value('status');
    }

    // ---- launch

    public function testAnEnrolledLearnerOpensTheExerciseAndGetsTheRunnerAddressAndNoToken(): void
    {
        $topic = $this->topic(['timeout_ms' => 7000, 'pass_score' => 60]);
        $student = $this->student();

        $data = $this->actingAs($student, 'api')->postJson("/api/code-exercises/launches/{$topic->getKey()}")->assertOk()->json('data');

        $this->assertSame('http://coffee.content.localhost/runtime/code-runner/1/runner.html', $data['runner']['url']);
        $this->assertSame(1, $data['runner']['version']);
        $this->assertSame('javascript', $data['exercise']['language']);
        $this->assertSame($this->starterFiles(), $data['exercise']['files']);
        $this->assertSame($this->visibleTests(), $data['exercise']['tests']);
        $this->assertSame(7000, $data['exercise']['timeout_ms']);
        $this->assertSame(60, $data['exercise']['pass_score']);
        $this->assertSame(['count' => 0, 'best_score' => null, 'last' => null], $data['attempts']);
        $this->assertStringNotContainsString('token', strtolower(json_encode($data)));
        $this->assertStringNotContainsString('?', $data['runner']['url']);
        $this->assertSame(ProgressStatus::IN_PROGRESS, $this->progressStatus($topic, $student));
    }

    public function testLaunchNeedsAccessAnExerciseAndAContentOrigin(): void
    {
        $topic = $this->topic();
        $this->postJson("/api/code-exercises/launches/{$topic->getKey()}")->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson("/api/code-exercises/launches/{$topic->getKey()}")->assertForbidden();

        $student = $this->student();
        $plain = Topic::factory()->create(['lesson_id' => $topic->lesson_id]);
        $this->actingAs($student, 'api')->postJson("/api/code-exercises/launches/{$plain->getKey()}")->assertNotFound();
        $this->actingAs($student, 'api')->postJson('/api/code-exercises/launches/99999999')->assertNotFound();

        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);
        $this->actingAs($student, 'api')->postJson("/api/code-exercises/launches/{$topic->getKey()}")->assertStatus(503);
    }

    public function testTheTypeCanBeSwitchedOffForATenant(): void
    {
        $topic = $this->topic();
        $student = $this->student();
        config(['ulams_code_exercises.enabled' => false]);

        $this->actingAs($student, 'api')->postJson("/api/code-exercises/launches/{$topic->getKey()}")->assertNotFound();
        $this->report($student, $topic, ['results' => $this->results(1)])->assertNotFound();
        $this->actingAs($student, 'api')->getJson("/api/code-exercises/topics/{$topic->getKey()}/attempts")->assertNotFound();
        $this->assertSame(0, CodeExerciseAttempt::query()->count());
    }

    // ---- attempts

    public function testAPassingAttemptCompletesTheTopicAndIsStoredUnverified(): void
    {
        Event::fake([CodeAttemptFinished::class]);
        $topic = $this->topic();
        $student = $this->student();

        $response = $this->report($student, $topic, ['results' => $this->results(3), 'files_hash' => str_repeat('a', 64), 'runner' => 'browser:1', 'duration_ms' => 120])
            ->assertCreated();

        $response->assertJsonPath('data.attempt.attempt_no', 1)
            ->assertJsonPath('data.attempt.passed', 3)
            ->assertJsonPath('data.attempt.total', 3)
            ->assertJsonPath('data.attempt.score', 100)
            ->assertJsonPath('data.attempt.verified', false)
            ->assertJsonPath('data.attempt.mode', 'practice')
            ->assertJsonPath('data.status', ProgressStatus::COMPLETE);
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressStatus($topic, $student));
        $attempt = CodeExerciseAttempt::query()->firstOrFail();
        $this->assertFalse($attempt->verified);
        $this->assertSame($student->getKey(), $attempt->user_id);
        $this->assertSame('browser:1', $attempt->runner);
        Event::assertDispatched(CodeAttemptFinished::class, fn (CodeAttemptFinished $e) => $e->userId === $student->getKey() && $e->topicId === $topic->getKey()
            && $e->attemptNo === 1 && $e->score === 100 && $e->passed === true && $e->verified === false);
    }

    public function testAFailingAttemptKeepsTheTopicInProgressUntilAPassingOne(): void
    {
        Event::fake([CodeAttemptFinished::class]);
        $topic = $this->topic();
        $student = $this->student();

        $this->report($student, $topic, ['results' => $this->results(1, 2)])
            ->assertCreated()
            ->assertJsonPath('data.attempt.score', 33)
            ->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS);
        $this->assertSame(ProgressStatus::IN_PROGRESS, $this->progressStatus($topic, $student));
        Event::assertDispatched(CodeAttemptFinished::class, fn (CodeAttemptFinished $e) => $e->passed === false && $e->attemptNo === 1);

        $this->report($student, $topic, ['results' => $this->results(3)])
            ->assertCreated()
            ->assertJsonPath('data.attempt.attempt_no', 2)
            ->assertJsonPath('data.status', ProgressStatus::COMPLETE);
        $this->assertSame(2, CodeExerciseAttempt::query()->count());
    }

    public function testThePassScoreDecidesWhatPassingMeans(): void
    {
        $topic = $this->topic(['pass_score' => 50]);
        $student = $this->student();

        $this->report($student, $topic, ['results' => $this->results(1, 2)])->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS);
        $this->report($student, $topic, ['results' => $this->results(2, 2)])->assertJsonPath('data.status', ProgressStatus::COMPLETE);
    }

    public function testOnAttemptCompletesAfterAnyRun(): void
    {
        $topic = $this->topic(['completion_rule' => 'on_attempt']);
        $student = $this->student();

        $this->report($student, $topic, ['results' => $this->results(0, 2)])->assertCreated()->assertJsonPath('data.status', ProgressStatus::COMPLETE);
    }

    public function testTheScoreIsRecomputedFromTheResultsNotTrustedFromTheClient(): void
    {
        $topic = $this->topic();
        $student = $this->student();

        // the claimed totals are not even fields: the server counts the rows
        $this->report($student, $topic, ['results' => $this->results(1, 3), 'passed' => 4, 'total' => 4, 'score' => 100, 'verified' => true, 'mode' => 'verified'])
            ->assertCreated()
            ->assertJsonPath('data.attempt.passed', 1)
            ->assertJsonPath('data.attempt.total', 4)
            ->assertJsonPath('data.attempt.score', 25)
            ->assertJsonPath('data.attempt.verified', false)
            ->assertJsonPath('data.attempt.mode', 'practice');
        $this->assertFalse(CodeExerciseAttempt::query()->firstOrFail()->verified);
    }

    public function testAttemptNumbersCountPerLearnerAndTopic(): void
    {
        $topic = $this->topic();
        $other = $this->topic();
        $a = $this->student();
        $b = $this->student();

        $this->report($a, $topic, ['results' => $this->results(0, 1)])->assertJsonPath('data.attempt.attempt_no', 1);
        $this->report($a, $topic, ['results' => $this->results(0, 1)])->assertJsonPath('data.attempt.attempt_no', 2);
        $this->report($b, $topic, ['results' => $this->results(0, 1)])->assertJsonPath('data.attempt.attempt_no', 1);
        $this->report($a, $other, ['results' => $this->results(0, 1)])->assertJsonPath('data.attempt.attempt_no', 1);
    }

    public function testTextIsCleanedAndCut(): void
    {
        $topic = $this->topic();
        $student = $this->student();

        $this->report($student, $topic, ['results' => [['name' => "adds\x07 up", 'status' => 'fail', 'message' => "boom\x00"]]])->assertCreated();

        $row = CodeExerciseAttempt::query()->firstOrFail()->results[0];
        $this->assertSame('adds up', $row['name']);
        $this->assertSame('boom', $row['message']);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function invalidReports(): array
    {
        $ok = [['name' => 'a', 'status' => 'pass']];

        return [
            'no results' => [[]],
            'empty results' => [['results' => []]],
            'too many results' => [['results' => array_fill(0, 201, ['name' => 'a', 'status' => 'pass'])]],
            'an unknown status' => [['results' => [['name' => 'a', 'status' => 'skipped']]]],
            'a missing name' => [['results' => [['status' => 'pass']]]],
            'an empty name' => [['results' => [['name' => '', 'status' => 'pass']]]],
            'a name that is too long' => [['results' => [['name' => str_repeat('a', 161), 'status' => 'pass']]]],
            'a message that is too long' => [['results' => [['name' => 'a', 'status' => 'fail', 'message' => str_repeat('a', 501)]]]],
            'an extra key in a result' => [['results' => [['name' => 'a', 'status' => 'pass', 'score' => 100]]]],
            'a name that is not text' => [['results' => [['name' => ['x'], 'status' => 'pass']]]],
            'a files hash that is not sha256' => [['results' => $ok, 'files_hash' => 'abc']],
            'a runner label with spaces' => [['results' => $ok, 'runner' => 'my runner']],
            'a negative duration' => [['results' => $ok, 'duration_ms' => -1]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidReports')]
    public function testAnInvalidReportIsRefusedAndNothingIsStored(array $body): void
    {
        $topic = $this->topic();
        $student = $this->student();

        $this->report($student, $topic, $body)->assertUnprocessable();

        $this->assertSame(0, CodeExerciseAttempt::query()->count());
        $this->assertNull($this->progressStatus($topic, $student));
    }

    public function testAttemptsAreThrottledPerLearner(): void
    {
        config(['ulams_code_exercises.limits.attempts_per_minute' => 3]);
        $topic = $this->topic();
        $student = $this->student();

        for ($i = 0; $i < 3; $i++) {
            $this->report($student, $topic, ['results' => $this->results(0, 1)])->assertCreated();
        }
        $this->report($student, $topic, ['results' => $this->results(0, 1)])->assertStatus(429);
        $this->assertSame(3, CodeExerciseAttempt::query()->count());
    }

    // ---- history

    public function testALearnerSeesOnlyTheirOwnAttemptsNewestFirst(): void
    {
        $topic = $this->topic();
        $a = $this->student();
        $b = $this->student();
        $this->report($a, $topic, ['results' => $this->results(0, 1)])->assertCreated();
        $this->report($a, $topic, ['results' => $this->results(1)])->assertCreated();
        $this->report($b, $topic, ['results' => $this->results(1)])->assertCreated();

        $mine = $this->actingAs($a, 'api')->getJson("/api/code-exercises/topics/{$topic->getKey()}/attempts")->assertOk()->json('data');

        $this->assertSame([2, 1], array_column($mine, 'attempt_no'));
        $this->assertCount(2, $mine);
        $this->assertArrayNotHasKey('user_id', $mine[0]);

        $launch = $this->actingAs($a, 'api')->postJson("/api/code-exercises/launches/{$topic->getKey()}")->json('data.attempts');
        $this->assertSame(2, $launch['count']);
        $this->assertSame(100, $launch['best_score']);
        $this->assertSame(2, $launch['last']['attempt_no']);
    }
}
