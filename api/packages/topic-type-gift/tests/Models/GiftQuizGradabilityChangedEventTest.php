<?php

namespace Ulams\TopicTypeGift\Tests\Models;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use Ulams\TopicTypeGift\Events\QuizGradabilityChangedEvent;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class GiftQuizGradabilityChangedEventTest extends TestCase
{
    use CreatesUsers;

    public function testEmitsWhenCountsToGradeEnabled(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => false]);

        Event::fake([QuizGradabilityChangedEvent::class]);

        $quiz->update(['counts_to_grade' => true]);

        Event::assertDispatched(
            QuizGradabilityChangedEvent::class,
            fn (QuizGradabilityChangedEvent $event) => $event->getQuiz()->is($quiz) && $event->countsToGrade() === true
        );
    }

    public function testEmitsWhenCountsToGradeDisabled(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => true]);

        Event::fake([QuizGradabilityChangedEvent::class]);

        $quiz->update(['counts_to_grade' => false]);

        Event::assertDispatched(
            QuizGradabilityChangedEvent::class,
            fn (QuizGradabilityChangedEvent $event) => $event->getQuiz()->is($quiz) && $event->countsToGrade() === false
        );
    }

    public function testDoesNotEmitWhenCountsToGradeIsUnchanged(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => true]);

        Event::fake([QuizGradabilityChangedEvent::class]);

        $quiz->update(['value' => 'a different value', 'counts_to_grade' => true]);

        Event::assertNotDispatched(QuizGradabilityChangedEvent::class);
    }

    public function testDoesNotEmitOnCreate(): void
    {
        Event::fake([QuizGradabilityChangedEvent::class]);

        GiftQuiz::factory()->create(['counts_to_grade' => true]);

        Event::assertNotDispatched(QuizGradabilityChangedEvent::class);
    }

    public function testEmitsViaAdminGiftQuizEndpoint(): void
    {
        $this->seed(TopicTypeGiftPermissionSeeder::class);
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => true]);

        Event::fake([QuizGradabilityChangedEvent::class]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), [
                'value' => $quiz->value,
                'counts_to_grade' => false,
            ])
            ->assertOk();

        Event::assertDispatched(
            QuizGradabilityChangedEvent::class,
            fn (QuizGradabilityChangedEvent $event) => $event->getQuiz()->is($quiz) && $event->countsToGrade() === false
        );
    }

    public function testEmitsViaGenericTopicUpdateEndpoint(): void
    {
        $this->seed(CoursesPermissionSeeder::class);
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => false]);
        $topic = Topic::factory()
            ->for(Lesson::factory()->for(Course::factory()))
            ->create([
                'topicable_type' => GiftQuiz::class,
                'topicable_id' => $quiz->getKey(),
            ]);

        Event::fake([QuizGradabilityChangedEvent::class]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/topics/' . $topic->getKey(), [
                'title' => $topic->title,
                'lesson_id' => $topic->lesson_id,
                'topicable_type' => GiftQuiz::class,
                'value' => $quiz->value,
                'counts_to_grade' => true,
            ])
            ->assertOk();

        Event::assertDispatched(
            QuizGradabilityChangedEvent::class,
            fn (QuizGradabilityChangedEvent $event) => $event->getQuiz()->is($quiz) && $event->countsToGrade() === true
        );
    }
}
