<?php

namespace Ulams\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Ai\Services\JsonStringStream;

class JsonStringStreamTest extends TestCase
{
    /** @param string[] $pieces */
    private function extract(array $pieces, string $field = 'answer'): string
    {
        $stream = new JsonStringStream($field);

        return implode('', array_map(fn ($p) => $stream->feed($p), $pieces));
    }

    public function testExtractsTheFieldAcrossChunks(): void
    {
        $this->assertSame('Hello world', $this->extract(['{"ans', 'wer": "Hel', 'lo wor', 'ld", "citations": []}']));
    }

    public function testEveryChunkSizeGivesTheSameText(): void
    {
        $text = "Line one\n\"quoted\" \\ back, tab\t, é, 日本語, 😀 end";
        $json = json_encode(['answer' => $text, 'citations' => ['c1'], 'in_scope' => true]);
        foreach ([1, 2, 3, 5, 7, 64] as $size) {
            $this->assertSame($text, $this->extract(str_split($json, $size)), "chunk size {$size}");
        }
    }

    public function testStopsAtTheClosingQuoteAndIgnoresLaterFields(): void
    {
        $stream = new JsonStringStream('answer');
        $this->assertSame('Done', $stream->feed('{"answer":"Done","note":"answer"}'));
        $this->assertTrue($stream->done());
        $this->assertSame('', $stream->feed('more'));
    }

    public function testNothingUntilTheFieldStarts(): void
    {
        $stream = new JsonStringStream('answer');
        $this->assertSame('', $stream->feed('{"citations":["c1"],'));
        $this->assertFalse($stream->done());
        $this->assertSame('Hi', $stream->feed('"answer":"Hi'));
    }

    public function testASplitEscapeWaitsForTheRest(): void
    {
        $stream = new JsonStringStream('answer');
        $this->assertSame('a', $stream->feed('{"answer":"a\\'));
        $this->assertSame("\nb", $stream->feed('nb'));
    }
}
