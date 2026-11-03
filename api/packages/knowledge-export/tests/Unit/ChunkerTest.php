<?php

namespace Ulams\KnowledgeExport\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\KnowledgeExport\Services\Chunker;

class ChunkerTest extends TestCase
{
    public function testSectionsBecomeChunksWithTheirHeadingPathAndCitations(): void
    {
        $chunks = (new Chunker(1, 600))->chunks([
            ['text' => "## Beans\n\nMedium roast works best.\n\n## Water\n\nUse filtered water.", 'citations' => ['frg_aaaaaaaaaaaa']],
        ]);

        $this->assertCount(2, $chunks);
        $this->assertSame(['Beans'], $chunks[0]['heading_path']);
        $this->assertSame(['Water'], $chunks[1]['heading_path']);
        $this->assertSame(['frg_aaaaaaaaaaaa'], $chunks[1]['citations']);
        $this->assertStringContainsString('filtered water', $chunks[1]['text']);
        $this->assertGreaterThan(0, $chunks[0]['tokens']);
    }

    public function testALoneLeadingTitleIsKeptInTheText(): void
    {
        $chunks = (new Chunker(1, 600))->chunks([['text' => "# Brewing\n\nGrind just before brewing.", 'citations' => []]]);

        $this->assertCount(1, $chunks);
        $this->assertStringStartsWith('# Brewing', $chunks[0]['text']);
    }

    public function testEmptySegmentsAreSkippedAndSegmentsKeepTheirOwnCitations(): void
    {
        $chunks = (new Chunker(1, 600))->chunks([
            ['text' => '   ', 'citations' => ['frg_x']],
            ['text' => 'First.', 'citations' => ['frg_aaaaaaaaaaaa']],
            ['text' => 'Second.', 'citations' => []],
        ]);

        $this->assertSame([['frg_aaaaaaaaaaaa'], []], array_column($chunks, 'citations'));
    }
}
