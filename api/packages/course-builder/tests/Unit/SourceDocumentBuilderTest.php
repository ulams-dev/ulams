<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use Ulams\CourseBuilder\Ingestion\ConvertedDocument;
use Ulams\CourseBuilder\Ingestion\FragmentId;
use Ulams\CourseBuilder\Ingestion\SourceConverter;
use Ulams\CourseBuilder\Ingestion\SourceDocumentBuilder;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Tests\TestCase;

class SourceDocumentBuilderTest extends TestCase
{
    private function source(): Source
    {
        return new Source(['id' => '01hzzzzzzzzzzzzzzzzzzzzzzz', 'original_name' => 'handbook.md']);
    }

    public function testSingleDocumentKeepsPhase2IdsAndHeadingPaths(): void
    {
        $md = "# Title\n\n## Alpha\n\nAlpha text about brewing.\n\n## Beta\n\nBeta text about grinding.\n";
        $built = (new SourceDocumentBuilder())->rows($this->source(), [new ConvertedDocument($md)]);

        $this->assertCount(2, $built['rows']);
        $this->assertSame(FragmentId::make('01hzzzzzzzzzzzzzzzzzzzzzzz', ['Alpha'], 0), $built['rows'][0]['id']);
        $this->assertSame(['Alpha'], $built['rows'][0]['heading_path']);
        $this->assertNull($built['rows'][0]['file_path']);
        $this->assertSame('Title', $built['meta']['title']);
        $this->assertSame(2, $built['meta']['fragments']);
        $this->assertSame($md, $built['markdown']);
    }

    public function testSeveralFilesGetTheFilePathAsFirstHeadingElementAndStableIds(): void
    {
        $a = new ConvertedDocument("## Rebase\n\nRebase rewrites history onto another base commit.\n", [], [], 'docs/rebase.md');
        $b = new ConvertedDocument("## Rebase\n\nA second file may reuse the same heading without colliding.\n", [], [], 'docs/merge.md');
        $built = (new SourceDocumentBuilder())->rows($this->source(), [$a, $b]);

        $this->assertSame(['docs/rebase.md', 'Rebase'], $built['rows'][0]['heading_path']);
        $this->assertSame(['docs/merge.md', 'Rebase'], $built['rows'][1]['heading_path']);
        $this->assertSame('docs/merge.md', $built['rows'][1]['file_path']);
        $this->assertNotSame($built['rows'][0]['id'], $built['rows'][1]['id']);
        $this->assertSame(2, $built['meta']['files']);
        $this->assertStringContainsString('<!-- file: docs/rebase.md -->', $built['markdown']);

        // the same inputs give the same ids (stable across revisions)
        $again = (new SourceDocumentBuilder())->rows($this->source(), [$a, $b]);
        $this->assertSame(array_column($built['rows'], 'id'), array_column($again['rows'], 'id'));
    }

    public function testEmptyInputIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SourceDocumentBuilder())->rows($this->source(), [new ConvertedDocument("   \n")]);
    }

    public function testConverterCleansMarkdown(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cbconv');
        file_put_contents($tmp, "\xEF\xBB\xBF---\ntitle: x\n---\nHello <!-- gone --> world\r\n");
        try {
            $doc = (new SourceConverter())->toMarkdown($tmp, 'markdown');
        } finally {
            @unlink($tmp);
        }
        $this->assertSame("Hello  world\n", $doc->markdown);
        $this->assertSame('markdown', $doc->metadata['kind']);
    }
}
