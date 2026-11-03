<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Ulams\CodeExercises\Events\CodeAttemptFinished;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\TestCase;

/** A finished run of a code exercise becomes one `code.attempt_finished` signal with numbers and flags only (ADR 0057, 0170). */
class CodeSignalTest extends TestCase
{
    public function testAFinishedAttemptBecomesOneSignalWithoutAnyCode(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        event(new CodeAttemptFinished($user->getKey(), $topic->getKey(), 77, 2, 33, false, false));
        event(new CodeAttemptFinished($user->getKey(), $topic->getKey(), 77, 2, 33, false, false));

        $signals = Signal::query()->where('type', SignalType::CODE_ATTEMPT_FINISHED)->get();
        $this->assertCount(1, $signals, 'the attempt id makes the signal idempotent');
        $signal = $signals->first();
        $this->assertSame('code', $signal->source);
        $this->assertSame('code_attempt_finished:77', $signal->source_ref);
        $this->assertSame($user->getKey(), $signal->user_id);
        $this->assertSame($course->getKey(), $signal->course_id);
        $this->assertSame('topic:' . $topic->getKey(), $signal->element_key);
        $this->assertSame(0.33, (float) $signal->value);
        $this->assertSame(['attempt_no' => 2, 'passed' => false, 'verified' => false], $signal->data);
    }

    public function testNothingIsRecordedWhenTheModuleIsOff(): void
    {
        config(['learner_insights.enabled' => false]);
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        event(new CodeAttemptFinished($user->getKey(), $topic->getKey(), 78, 1, 100, true, false));

        $this->assertSame(0, Signal::query()->where('type', SignalType::CODE_ATTEMPT_FINISHED)->count());
    }
}
