<?php

namespace Ulams\TopicTypeGift\Tests\Services;

use Ulams\Courses\Facades\Topic as TopicFacade;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypeGift\Tests\TestCase;

class GiftQuizContentDeleterTest extends TestCase
{
    private function topicWithQuiz(): array
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $quiz = GiftQuiz::factory()->create();
        GiftQuestion::factory()->count(2)->create(['topic_gift_quiz_id' => $quiz->getKey()]);
        $topic = Topic::factory()->create([
            'lesson_id' => $lesson->getKey(),
            'topicable_id' => $quiz->getKey(),
            'topicable_type' => GiftQuiz::class,
        ]);

        return [$topic, $quiz];
    }

    public function testDeletingTopicDeletesUnansweredQuizAndQuestions(): void
    {
        [$topic, $quiz] = $this->topicWithQuiz();

        TopicFacade::delete($topic->getKey());

        $this->assertNull(GiftQuiz::find($quiz->getKey()));
        $this->assertSame(0, GiftQuestion::where('topic_gift_quiz_id', $quiz->getKey())->count());
    }

    public function testAnsweredQuizIsKeptWhenItsTopicIsDeleted(): void
    {
        [$topic, $quiz] = $this->topicWithQuiz();
        QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz->getKey()]);

        TopicFacade::delete($topic->getKey());

        $this->assertNull(Topic::find($topic->getKey()));
        $this->assertNotNull(GiftQuiz::find($quiz->getKey()));
        $this->assertSame(2, GiftQuestion::where('topic_gift_quiz_id', $quiz->getKey())->count());
    }
}
