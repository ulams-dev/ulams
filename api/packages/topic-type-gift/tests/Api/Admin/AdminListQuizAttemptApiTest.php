<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Models\User;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;

class AdminListQuizAttemptApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);
    }

    public function testAdminQuizAttemptListUnauthorized(): void
    {
        $this->getJson('api/admin/quiz-attempts')
            ->assertUnauthorized();
    }

    public function testAdminQuizAttemptListFiltering(): void
    {
        $quiz = GiftQuiz::factory()->create();
        $quiz2 = GiftQuiz::factory()->create();

        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        QuizAttempt::factory()
            ->state(new Sequence(
                ['user_id' => $student->getKey(), 'topic_gift_quiz_id' => $quiz->getKey()],
                ['user_id' => $student->getKey(), 'topic_gift_quiz_id' => $quiz2->getKey()],
                ['user_id' => $student->getKey(), 'topic_gift_quiz_id' => $quiz2->getKey()],
                ['user_id' => $student2->getKey(), 'topic_gift_quiz_id' => $quiz2->getKey()],
            ))
            ->count(4)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')->getJson('api/admin/quiz-attempts?topic_gift_quiz_id=' . $quiz->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->makeAdmin(), 'api')->getJson('api/admin/quiz-attempts?user_id=' . $student->getKey())
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function testAdminQuizAttemptListFilteringByCourse(): void
    {
        $course1 = Course::factory()->create();
        $course2 = Course::factory()->create();

        $lesson1 = Lesson::factory()->state(['course_id' => $course1->getKey()])->create();
        $lesson2 = Lesson::factory()->state(['course_id' => $course2->getKey()])->create();

        $quiz1 = GiftQuiz::factory()->create();
        $quiz2 = GiftQuiz::factory()->create();
        $quiz3 = GiftQuiz::factory()->create();

        $topic1 = Topic::factory()->state(['lesson_id' => $lesson1->getKey()])->create();
        $topic2 = Topic::factory()->state(['lesson_id' => $lesson2->getKey()])->create();
        $topic3 = Topic::factory()->state(['lesson_id' => $lesson2->getKey()])->create();

        $topic1->topicable()->associate($quiz1)->save();
        $topic2->topicable()->associate($quiz2)->save();
        $topic3->topicable()->associate($quiz3)->save();

        QuizAttempt::factory()
            ->state(new Sequence(
                ['topic_gift_quiz_id' => $quiz1->getKey()], // course1
                ['topic_gift_quiz_id' => $quiz1->getKey()], // course1
                ['topic_gift_quiz_id' => $quiz2->getKey()], // course2
                ['topic_gift_quiz_id' => $quiz3->getKey()], // course2
                ['topic_gift_quiz_id' => $quiz3->getKey()], // course2
            ))
            ->count(5)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts?course_id=' . $course1->getKey())
            ->assertOk()
            ->assertJsonCount(2, 'data');


        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts?course_id=' . $course2->getKey())
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function testAdminQuizAttemptListSorting(): void
    {
        $student = $this->makeStudent();

        $quiz1 = GiftQuiz::factory()->create();

        $question1 = GiftQuestion::factory()->create([
            'topic_gift_quiz_id' => $quiz1->getKey(),
            'score' => 10,
        ]);
        $question2 = GiftQuestion::factory()->create([
            'topic_gift_quiz_id' => $quiz1->getKey(),
            'score' => 15,
        ]);

        $attempt1 = QuizAttempt::factory()
            ->create([
                'user_id' => $student->getKey(),
                'topic_gift_quiz_id' => $quiz1->getKey(),
                'end_at' => now()->subDays(5),
                'created_at' => now()->subDays(1),
            ]);
        AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $attempt1->getKey(),
            'topic_gift_question_id' => $question1->getKey(),
            'score' => 1,
        ]);
        AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $attempt1->getKey(),
            'topic_gift_question_id' => $question2->getKey(),
            'score' => 2,
        ]);

        $quiz2 = GiftQuiz::factory()->create();

        $question1 = GiftQuestion::factory()->create([
            'topic_gift_quiz_id' => $quiz2->getKey(),
            'score' => 10,
        ]);
        $question2 = GiftQuestion::factory()->create([
            'topic_gift_quiz_id' => $quiz2->getKey(),
            'score' => 8,
        ]);

        $attempt2 = QuizAttempt::factory()
            ->create([
                'user_id' => $student->getKey(),
                'topic_gift_quiz_id' => $quiz2->getKey(),
                'end_at' => now()->subDays(5),
                'created_at' => now(),
            ]);

        AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $attempt2->getKey(),
            'topic_gift_question_id' => $question1->getKey(),
            'score' => 8,
        ]);

        AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $attempt2->getKey(),
            'topic_gift_question_id' => $question2->getKey(),
            'score' => 6,
        ]);

        $response = $this->actingAs($this->makeAdmin(), 'api')->json('GET', 'api/admin/quiz-attempts', [
            'order_by' => 'created_at',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $attempt2->getKey());
        $this->assertTrue($response->json('data.1.id') === $attempt1->getKey());

        $response = $this->actingAs($this->makeAdmin(), 'api')->json('GET', 'api/admin/quiz-attempts', [
            'order_by' => 'created_at',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $attempt1->getKey());
        $this->assertTrue($response->json('data.1.id') === $attempt2->getKey());

        $response = $this->actingAs($this->makeAdmin(), 'api')->json('GET', 'api/admin/quiz-attempts', [
            'order_by' => 'result_score',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $attempt2->getKey());
        $this->assertTrue($response->json('data.1.id') === $attempt1->getKey());

        $response = $this->actingAs($this->makeAdmin(), 'api')->json('GET', 'api/admin/quiz-attempts', [
            'order_by' => 'result_score',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $attempt1->getKey());
        $this->assertTrue($response->json('data.1.id') === $attempt2->getKey());

        $response = $this->actingAs($this->makeAdmin(), 'api')->json('GET', 'api/admin/quiz-attempts', [
            'order_by' => 'max_score',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $attempt1->getKey());
        $this->assertTrue($response->json('data.1.id') === $attempt2->getKey());

        $response = $this->actingAs($this->makeAdmin(), 'api')->json('GET', 'api/admin/quiz-attempts', [
            'order_by' => 'max_score',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $attempt2->getKey());
        $this->assertTrue($response->json('data.1.id') === $attempt1->getKey());
    }

    public function testTutorQuizAttemptList(): void
    {
        // tutor
        $tutor = $this->makeInstructor();
        $course = Course::factory()->create();
        $course->authors()->sync($tutor);
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();
        $quiz = GiftQuiz::factory()->create();
        $topic = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();
        $topic->topicable()->associate($quiz)->save();

        // other
        $otherTopic = Topic::factory()
            ->for(Lesson::factory()
                ->for(Course::factory()))
            ->create();

        $otherTopic->lesson->course->authors()->sync($this->makeInstructor());
        $otherQuiz = GiftQuiz::factory()->create();
        $otherTopic->topicable()->associate($otherQuiz)->save();


        QuizAttempt::factory()
            ->state(new Sequence(
                ['topic_gift_quiz_id' => $quiz->getKey()],
                ['topic_gift_quiz_id' => $quiz->getKey()],
                ['topic_gift_quiz_id' => $quiz->getKey()],
                ['topic_gift_quiz_id' => $otherQuiz->getKey()],
                ['topic_gift_quiz_id' => $otherQuiz->getKey()],
            ))
            ->count(5)
            ->create();

        $this->actingAs($tutor, 'api')
            ->getJson('api/admin/quiz-attempts')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
