<?php

namespace Ulams\LivingCourse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LivingCourse\Analysis\NewLessonValidator;

class NewLessonValidatorTest extends TestCase
{
    private const FRAGMENT = 'frg_aaaaaaaaaaaa';

    private const MODULE = '01234567890123456789012345';

    /** @return array<string,mixed> */
    private function answer(array $override = []): array
    {
        return $override + [
            'moduleId' => self::MODULE,
            'moduleTitle' => null,
            'title' => 'Storing beans',
            'summary' => 'How to keep beans fresh.',
            'minutes' => 4,
            'objectives' => [['text' => 'Store beans so they stay fresh.', 'citations' => [self::FRAGMENT]]],
            'blocks' => [['kind' => 'paragraph', 'markdown' => 'Keep beans airtight.', 'citations' => [self::FRAGMENT], 'objectiveIndexes' => [0]]],
            'reply' => 'Done.',
        ];
    }

    private function errors(array $data): array
    {
        return NewLessonValidator::validate($data, [self::FRAGMENT], [self::MODULE]);
    }

    public function testAValidAnswerHasNoErrors(): void
    {
        $this->assertSame([], $this->errors($this->answer()));
    }

    public function testCitationsMustBeFragmentsOfTheNewSection(): void
    {
        $bad = $this->answer(['blocks' => [['kind' => 'paragraph', 'markdown' => 'x y', 'citations' => ['frg_bbbbbbbbbbbb'], 'objectiveIndexes' => [0]]]]);
        $this->assertStringContainsString('not a fragment', implode(' ', $this->errors($bad)));
    }

    public function testTheModuleMustExistOrBeNamed(): void
    {
        $this->assertNotSame([], $this->errors($this->answer(['moduleId' => '99999999999999999999999999'])));
        $this->assertNotSame([], $this->errors($this->answer(['moduleId' => null, 'moduleTitle' => ''])));
        $this->assertSame([], $this->errors($this->answer(['moduleId' => null, 'moduleTitle' => 'Storage'])));
        $this->assertNotSame([], $this->errors($this->answer(['moduleTitle' => 'Both'])));
    }

    public function testMarkupAndUnservedObjectivesAreRejected(): void
    {
        $this->assertNotSame([], $this->errors($this->answer(['title' => 'Storing <b>beans</b>'])));
        $this->assertNotSame([], $this->errors($this->answer(['summary' => 'See https://example.com'])));
        $html = $this->answer(['blocks' => [['kind' => 'paragraph', 'markdown' => '<script>alert(1)</script>', 'citations' => [self::FRAGMENT], 'objectiveIndexes' => [0]]]]);
        $this->assertNotSame([], $this->errors($html));
        $unserved = $this->answer(['objectives' => [['text' => 'One objective', 'citations' => [self::FRAGMENT]], ['text' => 'Another objective', 'citations' => [self::FRAGMENT]]]]);
        $this->assertStringContainsString('no block serves', implode(' ', $this->errors($unserved)));
    }
}
