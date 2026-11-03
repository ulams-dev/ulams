<?php

declare(strict_types=1);

namespace Ulams\LivingCourse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LivingCourse\Connectors\FetchResult;

class FetchResultQwenTest extends TestCase
{
    public function testUnchangedMethodCreatesUnchangedFetchResult()
    {
        $ref = 'some-ref';
        $fetchResult = FetchResult::unchanged($ref);

        $this->assertTrue($fetchResult->unchanged);
        $this->assertEquals($ref, $fetchResult->ref);
        $this->assertEmpty($fetchResult->files);
        $this->assertEmpty($fetchResult->metadata);
    }

    public function testConstructorSetsPropertiesCorrectly()
    {
        $unchanged = false;
        $ref = 'some-ref';
        $files = ['file1', 'file2'];
        $metadata = ['key' => 'value'];

        $fetchResult = new FetchResult($unchanged, $ref, $files, $metadata);

        $this->assertFalse($fetchResult->unchanged);
        $this->assertEquals($ref, $fetchResult->ref);
        $this->assertEquals($files, $fetchResult->files);
        $this->assertEquals($metadata, $fetchResult->metadata);
    }

    public function testConstructorDefaultsToEmptyFilesAndMetadata()
    {
        $unchanged = true;
        $ref = 'some-ref';

        $fetchResult = new FetchResult($unchanged, $ref);

        $this->assertTrue($fetchResult->unchanged);
        $this->assertEquals($ref, $fetchResult->ref);
        $this->assertEmpty($fetchResult->files);
        $this->assertEmpty($fetchResult->metadata);
    }
}
