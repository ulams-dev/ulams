<?php

declare(strict_types=1);

namespace Ulams\LivingCourse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LivingCourse\Connectors\FetchedFile;

class FetchedFileQwenTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $fetchedFile = new FetchedFile(
            path: 'example/path',
            bytes: 'example bytes',
            kind: 'markdown',
            blob: 'example blob'
        );

        $this->assertEquals('example/path', $fetchedFile->path);
        $this->assertEquals('example bytes', $fetchedFile->bytes);
        $this->assertEquals('markdown', $fetchedFile->kind);
        $this->assertEquals('example blob', $fetchedFile->blob);
    }

    public function testConstructorDefaultsKindToMarkdown()
    {
        $fetchedFile = new FetchedFile(
            path: 'example/path',
            bytes: 'example bytes'
        );

        $this->assertEquals('markdown', $fetchedFile->kind);
    }

    public function testConstructorDefaultsBlobToNull()
    {
        $fetchedFile = new FetchedFile(
            path: 'example/path',
            bytes: 'example bytes'
        );

        $this->assertNull($fetchedFile->blob);
    }
}
