<?php

namespace Ulams\Tutor\Tests;

use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Models\Course;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\Tutor\Models\Chunk;
use Ulams\Tutor\Support\GiftAnswers;

/** A published course built through the real builder pipeline, with helpers to learn from it and ask the tutor. */
abstract class CourseTestCase extends TestCase
{
    protected Session $session;

    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = $this->toApplied($this->author());
        $this->course = Course::query()->findOrFail($this->session->course_id);
        $this->course->update(['status' => 'published']);
    }

    protected function learner()
    {
        $user = $this->student();
        $user->courses()->attach($this->course->getKey());

        return $user;
    }

    protected function ask($user, string $text, array $extra = [])
    {
        return $this->actingAs($user, 'api')->postJson("/api/tutor/courses/{$this->course->getKey()}/messages", ['text' => $text] + $extra);
    }

    /** A word that occurs in the course content, so the synthetic answer finds a chunk. */
    protected function topicWord(): string
    {
        $text = Chunk::query()->where('course_id', $this->course->getKey())->orderBy('id')->value('text');
        if ($text === null) {
            app(\Ulams\Tutor\Services\TutorIndexer::class)->rebuild($this->course->getKey());
            $text = Chunk::query()->where('course_id', $this->course->getKey())->orderBy('id')->value('text');
        }
        foreach (preg_split('/[^\p{L}]+/u', $text) as $w) {
            if (mb_strlen($w) >= 7) {
                return $w;
            }
        }
        $this->fail('no usable word in the course content');
    }

    protected function openAttempt($user): array
    {
        $question = GiftQuestion::query()->whereIn('id', EntityMapEntry::query()->where('session_id', $this->session->id)->where('entity_type', 'gift_question')->pluck('entity_id'))->firstOrFail();
        QuizAttempt::query()->create(['topic_gift_quiz_id' => $question->topic_gift_quiz_id, 'user_id' => $user->getKey(), 'started_at' => now(), 'end_at' => null]);

        return GiftAnswers::correct($question->value);
    }
}
