<?php

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LearnerInsights\Signals\SignalType;

class SignalTypeTest extends TestCase
{
    public function testOnlyWhitelistedKeysSurvive(): void
    {
        $clean = SignalType::cleanData(SignalType::QUIZ_ANSWER, ['question_id' => 7, 'correct' => false, 'answer_text' => 'free text', 'email' => 'a@b.c']);

        $this->assertSame(['question_id' => 7, 'correct' => false], $clean);
    }

    public function testNonScalarValuesAreDroppedAndStringsCut(): void
    {
        $clean = SignalType::cleanData(SignalType::SCORM_STATUS, ['lesson_status' => str_repeat('x', 500), 'completion_status' => ['nested' => 1]]);

        $this->assertSame(['lesson_status' => str_repeat('x', 80)], $clean);
    }

    public function testUnknownTypesAreRefused(): void
    {
        $this->assertFalse(SignalType::isKnown('quiz.free_text'));
        $this->assertSame([], SignalType::cleanData('quiz.free_text', ['a' => 1]));
    }

    public function testEveryTypeHasAKnownShape(): void
    {
        foreach (SignalType::all() as $type) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+\.[a-z_]+$/', $type);
            $this->assertLessThanOrEqual(40, strlen($type));
        }
        $this->assertSame([SignalType::MEDIA_REPLAYED, SignalType::MEDIA_SEEKED_BACK], SignalType::CLIENT_TYPES);
    }

    public function testCodeAttemptsCarryOnlyNumbersAndFlags(): void
    {
        $clean = SignalType::cleanData(SignalType::CODE_ATTEMPT_FINISHED, ['attempt_no' => 3, 'passed' => false, 'verified' => false, 'source_code' => 'alert(1)', 'results' => [['name' => 'x']]]);

        $this->assertSame(['attempt_no' => 3, 'passed' => false, 'verified' => false], $clean);
        $this->assertContains('code', SignalType::SOURCES);
    }

    public function testChosenOptionsAreAShortListOfContentIdsAndNothingElse(): void
    {
        $clean = SignalType::cleanData(SignalType::QUIZ_ANSWER, ['question_id' => 7, 'correct' => false, 'chosen' => ['01hzx0000000000000000000a1', 'b2', 'with space', str_repeat('x', 41), 7, ['nested']]]);
        $this->assertSame(['question_id' => 7, 'correct' => false, 'chosen' => ['01hzx0000000000000000000a1', 'b2']], $clean);

        $many = SignalType::cleanData(SignalType::QUIZ_ANSWER, ['chosen' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']]);
        $this->assertCount(6, $many['chosen']);
        $this->assertSame([], SignalType::cleanData(SignalType::QUIZ_ANSWER, ['chosen' => 'free text from a learner']), 'a string is not a list of ids');
        $this->assertSame([], SignalType::cleanData(SignalType::TOPIC_OPENED, ['chosen' => ['a1']]), 'other signal types do not take it');
    }
}
