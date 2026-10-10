<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\CourseBuilder\Quality\AccessibilityCritic;
use Ulams\CourseBuilder\Quality\MechanicsCritic;

class DeterministicCriticsTest extends TestCase
{
    private function lesson(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'l1', 'title' => 'T', 'contentType' => 'richtext', 'blocks' => [['id' => 'b1', 'kind' => 'paragraph', 'markdown' => 'Text', 'citations' => ['frg_aaaaaaaaaaaa'], 'objectiveIds' => []]],
            'quiz' => null,
        ];
    }

    public function testMechanicsFindsBrokenQuestions(): void
    {
        $quiz = ['id' => 'q', 'questions' => [
            ['id' => 'q1', 'type' => 'single', 'stem' => 'Which?', 'options' => [['id' => 'a', 'text' => 'Same', 'correct' => true], ['id' => 'b', 'text' => 'same', 'correct' => true], ['id' => 'c', 'text' => 'Other', 'correct' => false]], 'explanation' => ''],
        ]];
        $issues = app(MechanicsCritic::class)->check($this->lesson(['quiz' => $quiz]));
        $text = implode("\n", array_column($issues, 'problem'));
        $this->assertStringContainsString('exactly one correct', $text);
        $this->assertStringContainsString('has no explanation', $text);
        $this->assertStringContainsString('same text', $text);
        $this->assertSame(['q1'], array_values(array_unique(array_column($issues, 'elementId'))));
        $this->assertSame([], app(MechanicsCritic::class)->check($this->lesson()));
    }

    public function testMechanicsChecksTheFormatSpecificParts(): void
    {
        $this->assertStringContainsString('code or an import', implode(' ', array_column(app(MechanicsCritic::class)->check($this->lesson(['contentType' => 'liascript', 'blocks' => [['id' => 'b1', 'kind' => 'paragraph', 'markdown' => 'x @eval', 'citations' => [], 'objectiveIds' => []]]])), 'problem')));
        $this->assertStringContainsString('has none', implode(' ', array_column(app(MechanicsCritic::class)->check($this->lesson(['contentType' => 'h5p'])), 'problem')));
        $badH5p = ['id' => 'i1', 'kind' => 'h5p', 'library' => 'H5P.Blanks', 'title' => 'x', 'data' => ['instruction' => 'Go', 'items' => [['text' => 'one {1} and {2}', 'blanks' => [['answer' => 'a', 'alternatives' => [], 'tip' => '']]]]]];
        $issues = app(MechanicsCritic::class)->check($this->lesson(['contentType' => 'h5p', 'interaction' => $badH5p]));
        $this->assertSame('i1', $issues[0]['elementId']);
    }

    public function testAccessibilityFindsHeadingJumpsAndEmptyLinks(): void
    {
        $lesson = $this->lesson(['blocks' => [['id' => 'b1', 'kind' => 'paragraph', 'markdown' => "### Fine\n\n##### Jump\n\n[](https://example.com)", 'citations' => [], 'objectiveIds' => []], ['id' => 'b2', 'kind' => 'table', 'markdown' => "| a | b |\n| 1 | 2 |", 'citations' => [], 'objectiveIds' => []]]]);
        $issues = (new AccessibilityCritic())->check($lesson);
        $text = implode("\n", array_column($issues, 'problem'));
        $this->assertStringContainsString('heading level jumps', $text);
        $this->assertStringContainsString('link has no text', $text);
        $this->assertStringContainsString('no header row', $text);
        $this->assertSame(['b1', 'b2'], array_values(array_unique(array_column($issues, 'elementId'))));
        $this->assertSame([], (new AccessibilityCritic())->check($this->lesson()));
    }
}
