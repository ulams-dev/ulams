<?php

namespace Ulams\TopicTypes\Tests\Models;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\Mocks\FakeS3;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypes\Facades\Markdown;
use Ulams\TopicTypes\Models\TopicContent\PDF;
use Ulams\TopicTypes\Tests\TestCase;

/**
 * Topic content that reads its own file on the server must work when the disk is S3 (ADR 0041): there
 * `Storage::path()` is only the object key and the public URL does not resolve inside a container.
 * The disk is the real `s3` driver over an in-memory bucket.
 */
class S3DiskTest extends TestCase
{
    use DatabaseTransactions;

    private FakeS3 $s3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s3 = FakeS3::install();
    }

    public function testAPdfTopicReadsItsPageCountFromAnS3Object(): void
    {
        $key = 'course/1/topic/1/pdf/document.pdf';
        Storage::put($key, (string) file_get_contents(__DIR__ . '/../mocks/pdf.pdf'));

        $pdf = new PDF(['value' => $key]);
        $pdf->processMetadataInfo();

        $this->assertGreaterThanOrEqual(1, $pdf->page_count);
        $this->assertGreaterThan(0, $pdf->length);
        $this->assertContains('GetObject', $this->s3->commands);
        $this->assertSame([], glob(sys_get_temp_dir() . '/ulams-copy-*') ?: [], 'the temporary copy is removed');
    }

    public function testAPdfTopicWithAMissingObjectLeavesTheMetadataUnset(): void
    {
        $pdf = new PDF(['value' => 'course/1/topic/1/pdf/missing.pdf']);
        $pdf->processMetadataInfo();

        $this->assertNull($pdf->page_count);
    }

    public function testMarkdownFromThisOriginIsStoredAndSizedOnS3(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory(['course_id' => $course->getKey()])->create();
        $topic = Topic::factory(['lesson_id' => $lesson->getKey()])->create();
        $prefix = sprintf('course/%d/topic/%d/', $course->id, $topic->id);
        $directory = public_path('ulams-markdown-s3-test');
        @mkdir($directory, 0777, true);
        // a link whose name has no image extension makes the helper look at the image to name the copy
        file_put_contents($directory . '/pixel', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        try {
            $result = Markdown::convertAssetPaths('![Pixel](' . url('ulams-markdown-s3-test/pixel') . ')', $prefix);
        } finally {
            @unlink($directory . '/pixel');
            @rmdir($directory);
        }

        $this->assertCount(1, $result['results']);
        $this->assertArrayHasKey($prefix . 'pixel', $this->s3->objects);
        $this->assertArrayHasKey($prefix . 'pixel.png', $this->s3->objects, 'named after the type found in the image');
        $this->assertStringContainsString($prefix . 'pixel.png', $result['value']);
    }

    public function testMarkdownWithAnUnreadableImageKeepsTheLinkInsteadOfFailing(): void
    {
        $directory = public_path('ulams-markdown-s3-test');
        @mkdir($directory, 0777, true);
        file_put_contents($directory . '/notes', 'plain text, not an image');

        try {
            $result = Markdown::convertAssetPaths('![Notes](' . url('ulams-markdown-s3-test/notes') . ')', 'course/9/topic/9/');
        } finally {
            @unlink($directory . '/notes');
            @rmdir($directory);
        }

        $this->assertCount(1, $result['results']);
        $this->assertArrayNotHasKey('course/9/topic/9/notes.png', $this->s3->objects);
    }
}
