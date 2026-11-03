<?php

declare(strict_types=1);

namespace Ulams\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Ai\Dto\ContentBlock;

final class ContentBlockMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testTextBlockIsNotDocument(): void
    {
        $block = ContentBlock::text('Hello, World!');
        self::assertFalse($block->isDocument());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTextBlockFingerprintIsText(): void
    {
        $block = ContentBlock::text('Hello, World!');
        self::assertSame('Hello, World!', $block->fingerprint());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPdfBlockIsDocument(): void
    {
        $block = ContentBlock::pdf('base64data');
        self::assertTrue($block->isDocument());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPdfBlockFingerprintIsHashedData(): void
    {
        $block = ContentBlock::pdf('base64data');
        self::assertSame('document:' . hash('sha256', 'base64data'), $block->fingerprint());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTextBlockMediaTypeIsTextPlain(): void
    {
        $block = ContentBlock::text('Hello, World!');
        self::assertSame('text/plain', $block->mediaType);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPdfBlockMediaTypeIsApplicationPdf(): void
    {
        $block = ContentBlock::pdf('base64data');
        self::assertSame('application/pdf', $block->mediaType);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTextBlockCanBeCacheable(): void
    {
        $block = ContentBlock::text('Hello, World!', true);
        self::assertTrue($block->cache);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPdfBlockCanBeCacheable(): void
    {
        $block = ContentBlock::pdf('base64data', true);
        self::assertTrue($block->cache);
    }
}
