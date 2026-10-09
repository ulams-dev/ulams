<?php

namespace Ulams\TopicTypeLayout\Tests\Feature;

use Ulams\TopicTypeLayout\Services\LayoutDocumentValidator;
use Ulams\TopicTypeLayout\Tests\TestCase;

class LayoutDocumentValidatorTest extends TestCase
{
    private function validator(): LayoutDocumentValidator
    {
        return app(LayoutDocumentValidator::class);
    }

    public function testTheApprovedSetIsTheNineLearnerLayoutComponents(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Callout', 'Steps', 'ComparisonTable', 'H5PFrame', 'LiaScriptLesson', 'Timeline', 'FlipCards', 'CodeBlock', 'PracticeActivity'],
            $this->validator()->components()
        );
        $this->assertEqualsCanonicalizing($this->validator()->components(), $this->exampleNames());
    }

    public function testEachExampleIsValidAndEachInvalidExampleIsNot(): void
    {
        foreach ($this->exampleNames() as $name) {
            $example = $this->example($name);
            $this->assertSame([], $this->validator()->validate([['component' => $name, 'props' => $example['props']]]), $name);
            $this->assertNotSame([], $this->validator()->validate([['component' => $name, 'props' => $example['invalid']]]), "{$name} (invalid)");
        }
    }

    public function testErrorsCarryTheNodeIndexAndThePropPath(): void
    {
        $errors = $this->validator()->validate([
            ['component' => 'Callout', 'props' => ['text' => 'fine']],
            ['component' => 'Timeline', 'props' => ['items' => 'x']],
        ]);

        $this->assertNotSame([], $errors);
        foreach ($errors as $error) {
            $this->assertStringStartsWith('/1/props', $error);
        }
    }

    public function testTheNodeCountIsLimited(): void
    {
        $node = ['component' => 'Callout', 'props' => ['text' => 'x']];

        $this->assertSame([], $this->validator()->validate(array_fill(0, LayoutDocumentValidator::MAX_NODES, $node)));
        $this->assertNotSame([], $this->validator()->validate(array_fill(0, LayoutDocumentValidator::MAX_NODES + 1, $node)));
    }

    public function testEmptyPropsAreAnObjectNotAList(): void
    {
        $errors = $this->validator()->validate([['component' => 'Callout', 'props' => []]]);

        $this->assertNotSame([], $errors);
        $this->assertStringContainsString('text', implode(' ', $errors));
    }

    /**
     * The API copy of the manifest must equal the one the front generates
     * (`yarn workspace @ulams/ui learner-manifest`; CI also runs it with --check). Skipped where the
     * front is not mounted next to the API.
     */
    public function testTheManifestCopyMatchesTheFrontCatalogue(): void
    {
        $source = base_path('../front/ui/catalogue/learner-layout-manifest.json');
        if (!is_file($source)) {
            $this->markTestSkipped('front/ui is not available next to the API');
        }

        $this->assertSame(
            file_get_contents($source),
            file_get_contents(__DIR__ . '/../../resources/learner-layout-manifest.json'),
            'api/packages/topic-type-layout/resources/learner-layout-manifest.json is out of date: run yarn workspace @ulams/ui learner-manifest'
        );
    }
}
