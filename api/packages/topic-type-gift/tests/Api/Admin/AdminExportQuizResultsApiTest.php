<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Export\QuizResultsExport;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Maatwebsite\Excel\Facades\Excel;

class AdminExportQuizResultsApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);
    }

    private function attachQuizToCourse(GiftQuiz $quiz, Course $course, ?string $topicTitle = null): void
    {
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();
        $topic = Topic::factory()->state(array_filter([
            'lesson_id' => $lesson->getKey(),
            'title' => $topicTitle,
        ]))->create();
        $topic->topicable()->associate($quiz)->save();
    }

    public function testExportUnauthorized(): void
    {
        $course = Course::factory()->create();

        $this->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey())
            ->assertUnauthorized();
    }

    public function testExportRequiresCourseId(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export')
            ->assertUnprocessable();
    }

    private function courseWithOneAttempt(): Course
    {
        $course = Course::factory()->create();
        $quiz = GiftQuiz::factory()->create(['value' => 'Format quiz']);
        $this->attachQuizToCourse($quiz, $course);
        QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz->getKey()]);

        return $course;
    }

    public function testExportDefaultsToXlsx(): void
    {
        Excel::fake();

        $course = $this->courseWithOneAttempt();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey())
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xlsx');
    }

    public function testExportCanReturnXls(): void
    {
        Excel::fake();

        $course = $this->courseWithOneAttempt();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey() . '&format=xls')
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xls');
    }

    public function testExportCanReturnXlsxExplicitly(): void
    {
        Excel::fake();

        $course = $this->courseWithOneAttempt();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey() . '&format=xlsx')
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xlsx');
    }

    public function testExportFormatIsCaseInsensitive(): void
    {
        Excel::fake();

        $course = $this->courseWithOneAttempt();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey() . '&format=XLS')
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xls');
    }

    public function testExportRejectsUnsupportedFormat(): void
    {
        Excel::fake();

        $course = $this->courseWithOneAttempt();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey() . '&format=csv')
            ->assertUnprocessable();
    }

    public function testExportWholeCourseHasSheetPerQuiz(): void
    {
        Excel::fake();

        $course = Course::factory()->create();

        $quiz1 = GiftQuiz::factory()->create();
        $quiz2 = GiftQuiz::factory()->create();
        // A quiz belonging to a different course must not appear.
        $otherQuiz = GiftQuiz::factory()->create();

        $this->attachQuizToCourse($quiz1, $course, 'First quiz');
        $this->attachQuizToCourse($quiz2, $course, 'Second quiz');
        $this->attachQuizToCourse($otherQuiz, Course::factory()->create(), 'Other course quiz');

        QuizAttempt::factory()
            ->state(new Sequence(
                ['topic_gift_quiz_id' => $quiz1->getKey()],
                ['topic_gift_quiz_id' => $quiz2->getKey()],
                ['topic_gift_quiz_id' => $otherQuiz->getKey()],
            ))
            ->count(3)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey())
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xlsx', function (QuizResultsExport $export) {
            $sheets = $export->sheets();
            $this->assertCount(2, $sheets);
            $titles = array_map(fn ($sheet) => $sheet->title(), $sheets);
            $this->assertEqualsCanonicalizing(['First quiz', 'Second quiz'], $titles);

            return true;
        });
    }

    public function testExportSingleQuizHasOneSheet(): void
    {
        Excel::fake();

        $course = Course::factory()->create();

        $quiz1 = GiftQuiz::factory()->create();
        $quiz2 = GiftQuiz::factory()->create();
        $this->attachQuizToCourse($quiz1, $course, 'Quiz one');
        $this->attachQuizToCourse($quiz2, $course, 'Quiz two');

        QuizAttempt::factory()->count(2)->create(['topic_gift_quiz_id' => $quiz1->getKey()]);
        QuizAttempt::factory()->count(2)->create(['topic_gift_quiz_id' => $quiz2->getKey()]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey() . '&topic_gift_quiz_id=' . $quiz1->getKey())
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xlsx', function (QuizResultsExport $export) {
            $sheets = $export->sheets();
            $this->assertCount(1, $sheets);
            $this->assertEquals('Quiz one', $sheets[0]->title());

            return true;
        });
    }

    public function testExportContainsAllAttemptsOfAllStudents(): void
    {
        Excel::fake();

        $course = Course::factory()->create();
        $quiz = GiftQuiz::factory()->create(['value' => 'Attempts quiz']);
        $this->attachQuizToCourse($quiz, $course);

        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();

        // student1 has two attempts, student2 has one - three rows total.
        QuizAttempt::factory()
            ->state(new Sequence(
                ['user_id' => $student1->getKey(), 'created_at' => now()->subDays(2)],
                ['user_id' => $student1->getKey(), 'created_at' => now()->subDay()],
                ['user_id' => $student2->getKey(), 'created_at' => now()->subDay()],
            ))
            ->count(3)
            ->create(['topic_gift_quiz_id' => $quiz->getKey()]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey())
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xlsx', function (QuizResultsExport $export) use ($student1) {
            $rows = $export->sheets()[0]->collection();
            $this->assertCount(3, $rows);

            // Attempt number is a per-student sequence.
            $student1Rows = $rows->where('email', $student1->email)->values();
            $this->assertEqualsCanonicalizing([1, 2], $student1Rows->pluck('attempt_number')->all());

            return true;
        });
    }

    public function testPassedColumnIsComputedFromMinPassScore(): void
    {
        Excel::fake();

        $course = Course::factory()->create();
        $quiz = GiftQuiz::factory()->create(['value' => 'Passing quiz', 'min_pass_score' => 10]);
        $this->attachQuizToCourse($quiz, $course);

        $question = GiftQuestion::factory()->create([
            'topic_gift_quiz_id' => $quiz->getKey(),
            'score' => 10,
        ]);

        $passing = $this->makeStudent();
        $failing = $this->makeStudent();

        $passingAttempt = QuizAttempt::factory()->create([
            'user_id' => $passing->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
            'end_at' => now()->subDay(),
        ]);
        AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $passingAttempt->getKey(),
            'topic_gift_question_id' => $question->getKey(),
            'score' => 10,
        ]);

        $failingAttempt = QuizAttempt::factory()->create([
            'user_id' => $failing->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
            'end_at' => now()->subDay(),
        ]);
        AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $failingAttempt->getKey(),
            'topic_gift_question_id' => $question->getKey(),
            'score' => 4,
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $course->getKey())
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xlsx', function (QuizResultsExport $export) use ($passing, $failing) {
            $rows = $export->sheets()[0]->collection();

            $passingRow = $rows->firstWhere('email', $passing->email);
            $failingRow = $rows->firstWhere('email', $failing->email);

            $this->assertEquals(10, $passingRow['result_score']);
            $this->assertEquals(10, $passingRow['max_score']);
            $this->assertEquals(100.0, $passingRow['result_percent']);
            $this->assertEquals(__('Yes'), $passingRow['is_passed']);

            $this->assertEquals(4, $failingRow['result_score']);
            $this->assertEquals(__('No'), $failingRow['is_passed']);

            return true;
        });
    }

    public function testTutorCanOnlyExportOwnCourse(): void
    {
        Excel::fake();

        $tutor = $this->makeInstructor();

        $ownCourse = Course::factory()->create();
        $ownCourse->authors()->sync($tutor);
        $ownQuiz = GiftQuiz::factory()->create(['value' => 'Own quiz']);
        $this->attachQuizToCourse($ownQuiz, $ownCourse);
        QuizAttempt::factory()->create(['topic_gift_quiz_id' => $ownQuiz->getKey()]);

        $foreignCourse = Course::factory()->create();
        $foreignCourse->authors()->sync($this->makeInstructor());
        $foreignQuiz = GiftQuiz::factory()->create(['value' => 'Foreign quiz']);
        $this->attachQuizToCourse($foreignQuiz, $foreignCourse);
        QuizAttempt::factory()->create(['topic_gift_quiz_id' => $foreignQuiz->getKey()]);

        // Own course - one sheet.
        $this->actingAs($tutor, 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $ownCourse->getKey())
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xlsx', function (QuizResultsExport $export) {
            $this->assertCount(1, $export->sheets());

            return true;
        });

        // Foreign course - the tutor is not an author, so nothing is exported.
        $this->actingAs($tutor, 'api')
            ->getJson('api/admin/quiz-attempts/export?course_id=' . $foreignCourse->getKey())
            ->assertOk();

        Excel::assertDownloaded('quiz-results.xlsx', function (QuizResultsExport $export) {
            $this->assertCount(0, $export->sheets());

            return true;
        });
    }
}
