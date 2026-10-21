<?php

namespace Ulams\Tutor\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Tests\Support\ModelRequests;
use Ulams\Tutor\Tests\CourseTestCase;

/**
 * "Signals never reach an LLM" (CLAUDE.md non-negotiables). A learner with a recognisable name and
 * e-mail address, enrolled in a second course and struggling in this one, asks the tutor a question:
 * the request holds the course's passages and the question, wrapped as untrusted text, and nothing
 * else of the learner (no id, name, address, other course, signal type or status), and the call is
 * logged without a user.
 */
class LlmMinimisationTest extends CourseTestCase
{
    private const FIRST = 'Zeldaquill';

    private const LAST = 'Featherstone';

    private const EMAIL = 'zeldaquill.featherstone@learners.example.test';

    private const OTHER_COURSE = 'Zebra Quantum Pottery Masterclass';

    public function testTheTutorRequestCarriesOnlyTheCourseAndTheQuestion(): void
    {
        $user = $this->learner();
        $user->forceFill(['first_name' => self::FIRST, 'last_name' => self::LAST, 'email' => self::EMAIL])->save();
        $user->courses()->attach(Course::factory()->create(['status' => 'published', 'title' => self::OTHER_COURSE])->getKey());
        // the learner's history in this course: failed attempts, so a status exists
        foreach ([1, 2] as $i) {
            app(\Ulams\LearnerInsights\Services\SignalRecorder::class)->record(new \Ulams\LearnerInsights\Signals\SignalData(
                $user->getKey(), \Ulams\LearnerInsights\Signals\SignalType::QUIZ_ANSWER, 'gift', 'tutor-min:' . $i, now(), $this->course->getKey(), null, 3, 0.0, ['question_id' => 3, 'correct' => false],
            ));
        }

        $this->ask($user, 'What does the course say about ' . $this->topicWord() . '?')->assertOk();

        $sent = array_values(array_filter($this->fake()->sent(), fn ($r) => $r->task === 'tutor'));
        $this->assertNotEmpty($sent, 'no tutor request was recorded, the test would pass without checking anything');
        $forbidden = [self::FIRST, self::LAST, self::EMAIL, 'learners.example.test', self::OTHER_COURSE, ...ModelRequests::learnerVocabulary()];
        foreach ($sent as $request) {
            $text = ModelRequests::text($request);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsStringIgnoringCase($needle, $text, "a tutor request carries \"$needle\"");
            }
            $this->assertStringContainsString('untrusted="true"', $text, 'the question is wrapped as untrusted text');
        }
    }

    public function testEveryLoggedTutorCallHasNoUser(): void
    {
        $user = $this->learner();
        $this->ask($user, 'What does the course say about ' . $this->topicWord() . '?')->assertOk();
        $this->ask($user, 'And one more thing about ' . $this->topicWord() . '?')->assertOk();

        $calls = AiCall::query()->where('task', 'tutor')->get();
        $this->assertCount(2, $calls);
        foreach ($calls as $call) {
            $this->assertNull($call->user_id);
        }
    }
}
