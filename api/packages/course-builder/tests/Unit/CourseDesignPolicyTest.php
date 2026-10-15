<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use Ulams\CourseBuilder\CourseDesign\CourseDesignPolicy;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\H5P\Testing\H5PServiceFake;

class CourseDesignPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        H5PServiceFake::$libraries = [
            'H5P.Blanks' => '1.14',
            'H5P.DragText' => '1.10',
            'H5P.Dialogcards' => '1.9',
            'H5P.MultiChoice' => '1.16',
        ];
        H5PServiceFake::fake();
    }

    public function testPolicyIsValidCompleteAndUsesTheApprovedShells(): void
    {
        $document = app(CourseDesignPolicy::class)->document();
        $ids = array_column($document['capabilities'], 'id');
        $h5p = array_values(array_filter($ids, fn (string $id) => str_starts_with($id, 'h5p.')));

        $this->assertSame(1, $document['version']);
        $this->assertSame(['course', 'scroll', 'slides'], array_column($document['shells'], 'id'));
        $this->assertCount(54, $h5p);
        $this->assertCount(count($ids), array_unique($ids), 'Capability ids must be unique.');
        $this->assertSame(
            ['viewed', 'progressed', 'attempted', 'answered', 'scored', 'completed', 'passed', 'failed', 'resumed'],
            $document['trackingEvents'],
        );
    }

    public function testRuntimeViewDoesNotTreatInventoryAsGenerationPermission(): void
    {
        $policy = app(CourseDesignPolicy::class);
        $byId = collect($policy->capabilities())->keyBy('id');
        $generated = collect($policy->apiGeneratable())->pluck('id')->all();

        $this->assertTrue($byId['h5p.blanks']['available']);
        $this->assertTrue($byId['h5p.multiple-choice']['available']);
        $this->assertSame('author-import', $byId['h5p.multiple-choice']['authoring']);
        $this->assertContains('h5p.blanks', $generated);
        $this->assertNotContains('h5p.multiple-choice', $generated);
        $this->assertFalse($byId['native.simulation']['available']);
    }

    public function testModelSummaryContainsOnlyApiGeneratableInstalledBlocks(): void
    {
        $summary = app(CourseDesignPolicy::class)->modelSummary();

        $this->assertStringContainsString('course:', $summary);
        $this->assertStringContainsString('scroll:', $summary);
        $this->assertStringContainsString('slides:', $summary);
        $this->assertStringContainsString('h5p.blanks', $summary);
        $this->assertStringNotContainsString('h5p.multiple-choice', $summary);
        $this->assertStringNotContainsString('native.simulation', $summary);
    }
}
