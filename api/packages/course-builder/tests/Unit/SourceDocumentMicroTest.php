<?php

declare(strict_types=1);

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\Ingestion\SourceDocument;

final class SourceDocumentMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testMarkdownIsStoredUnchanged(): void
    {
        $markdown = "# Test Document\n\nThis is a test.";
        $fragments = [
            ['heading_path' => ['Test Document'], 'text' => 'This is a test.', 'ordinal' => 1],
        ];
        $metadata = ['title' => 'Test Document'];

        $sourceDocument = new SourceDocument($markdown, $fragments, $metadata);

        self::assertSame($markdown, $sourceDocument->markdown);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testFragmentsAreStoredUnchanged(): void
    {
        $markdown = "# Test Document\n\nThis is a test.";
        $fragments = [
            ['heading_path' => ['Test Document'], 'text' => 'This is a test.', 'ordinal' => 1],
        ];
        $metadata = ['title' => 'Test Document'];

        $sourceDocument = new SourceDocument($markdown, $fragments, $metadata);

        self::assertSame($fragments, $sourceDocument->fragments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMetadataIsStoredUnchanged(): void
    {
        $markdown = "# Test Document\n\nThis is a test.";
        $fragments = [
            ['heading_path' => ['Test Document'], 'text' => 'This is a test.', 'ordinal' => 1],
        ];
        $metadata = ['title' => 'Test Document'];

        $sourceDocument = new SourceDocument($markdown, $fragments, $metadata);

        self::assertSame($metadata, $sourceDocument->metadata);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMultipleFragmentsAreStoredUnchanged(): void
    {
        $markdown = "# Test Document\n\nThis is a test.\n\nAnother section.";
        $fragments = [
            ['heading_path' => ['Test Document'], 'text' => 'This is a test.', 'ordinal' => 1],
            ['heading_path' => ['Another section'], 'text' => 'Another section.', 'ordinal' => 2],
        ];
        $metadata = ['title' => 'Test Document'];

        $sourceDocument = new SourceDocument($markdown, $fragments, $metadata);

        self::assertSame($fragments, $sourceDocument->fragments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testEmptyFragmentsAreStoredUnchanged(): void
    {
        $markdown = "# Test Document\n\nThis is a test.";
        $fragments = [];
        $metadata = ['title' => 'Test Document'];

        $sourceDocument = new SourceDocument($markdown, $fragments, $metadata);

        self::assertSame($fragments, $sourceDocument->fragments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testEmptyMetadataAreStoredUnchanged(): void
    {
        $markdown = "# Test Document\n\nThis is a test.";
        $fragments = [
            ['heading_path' => ['Test Document'], 'text' => 'This is a test.', 'ordinal' => 1],
        ];
        $metadata = [];

        $sourceDocument = new SourceDocument($markdown, $fragments, $metadata);

        self::assertSame($metadata, $sourceDocument->metadata);
    }
}
