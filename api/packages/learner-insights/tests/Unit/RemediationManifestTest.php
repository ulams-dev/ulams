<?php

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LearnerInsights\Remediation\RemediationManifest;

/** Stored remediation blocks are checked against the closed catalogue schemas of the learner UI. */
class RemediationManifestTest extends TestCase
{
    private const PRACTICE = [
        'stem' => 'How much coffee for 480 ml at 1:16?',
        'options' => [['text' => '20 g', 'correct' => false], ['text' => '30 g', 'correct' => true]],
        'explanation' => '480 divided by 16 is 30.',
    ];

    public function testTheApprovedBlocksAreTheFourTheGeneratorWrites(): void
    {
        $this->assertSame(['Prose', 'WorkedExample', 'PracticeQuestion', 'ElaborativeQuestion'], (new RemediationManifest())->components());
    }

    public function testTheStoredShapeOfEveryBlockKindIsValid(): void
    {
        $blocks = [
            ['component' => 'Prose', 'props' => ['title' => 'Another way', 'markdown' => 'Text']],
            ['component' => 'WorkedExample', 'props' => ['title' => 'Steps', 'markdown' => 'Step 1']],
            ['component' => 'PracticeQuestion', 'props' => ['title' => 'Try', 'markdown' => 'Use it', 'practice' => self::PRACTICE]],
            ['component' => 'ElaborativeQuestion', 'props' => ['title' => 'Say it', 'markdown' => 'Why?']],
        ];

        $this->assertSame([], (new RemediationManifest())->validate($blocks));
    }

    public function testABlockOutsideTheCatalogueOrWithUnknownPropsIsRejected(): void
    {
        $manifest = new RemediationManifest();

        $this->assertNotSame([], $manifest->validate([['component' => 'Hero', 'props' => ['title' => 'x']]]));
        $this->assertNotSame([], $manifest->validate([['component' => 'Prose', 'props' => ['markdown' => 'x', 'onclick' => 'alert(1)']]]));
        $this->assertNotSame([], $manifest->validate([['component' => 'WorkedExample', 'props' => ['title' => 'only a title']]]));
    }

    public function testErrorsNameTheBlockAndThePropPath(): void
    {
        $errors = (new RemediationManifest())->validate([
            ['component' => 'Prose', 'props' => ['markdown' => 'fine']],
            ['component' => 'PracticeQuestion', 'props' => ['title' => 't', 'markdown' => 'm', 'practice' => ['stem' => 's', 'options' => [['text' => 'only', 'correct' => true]], 'explanation' => 'e']]],
        ]);

        $this->assertStringContainsString('/1/props', implode(' ', $errors));
        $this->assertStringContainsString('options', implode(' ', $errors));
    }

    public function testAGeneratedProseBlockIsLimitedToTheGenerationLength(): void
    {
        $manifest = new RemediationManifest();

        $this->assertSame([], $manifest->validate([['component' => 'Prose', 'props' => ['markdown' => str_repeat('a', 1800)]]]));
        $this->assertNotSame([], $manifest->validate([['component' => 'Prose', 'props' => ['markdown' => str_repeat('a', 1801)]]]));
    }

    public function testPracticeNeedsTwoToFiveOptions(): void
    {
        $manifest = new RemediationManifest();
        $with = fn (array $options) => [['component' => 'PracticeQuestion', 'props' => ['title' => 't', 'markdown' => 'm', 'practice' => ['stem' => 's', 'options' => $options, 'explanation' => 'e'] ]]];
        $option = fn (int $i) => ['text' => "o$i", 'correct' => $i === 0];

        $this->assertNotSame([], $manifest->validate($with([$option(0)])));
        $this->assertSame([], $manifest->validate($with(array_map($option, range(0, 4)))));
        $this->assertNotSame([], $manifest->validate($with(array_map($option, range(0, 5)))));
    }

    public function testTheCopyMatchesTheFrontCatalogue(): void
    {
        $source = __DIR__ . '/../../../../../front/ui/catalogue/remediation-manifest.json';
        if (!is_file($source)) {
            $this->markTestSkipped('front/ui is not available next to the API');
        }

        $this->assertSame(
            file_get_contents($source),
            file_get_contents(__DIR__ . '/../../resources/remediation-manifest.json'),
            'api/packages/learner-insights/resources/remediation-manifest.json is out of date: run yarn workspace @ulams/ui remediation-manifest'
        );
    }
}
