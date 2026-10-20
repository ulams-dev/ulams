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
}
