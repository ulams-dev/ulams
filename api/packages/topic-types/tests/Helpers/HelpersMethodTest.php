<?php

namespace Tests\Helpers;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypes\Facades\Markdown;
use Ulams\TopicTypes\Facades\Path;
use Ulams\TopicTypes\Services\TopicTypeService;
use Ulams\TopicTypes\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;

class HelpersMethodTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->course = Course::factory()->create();
        $this->lesson = Lesson::factory([
            'course_id' => $this->course->getKey()
        ])->create();
        $this->topic = Topic::factory([
            'lesson_id' => $this->lesson->getKey()
        ])->create();
    }

    public function testConvertImagesApi(): void
    {
        $topic = $this->topic;
        $course = $topic->lesson->course;
        $file = 'test.jpg';
        $destinationPrefix = sprintf('course/%d/topic/%d/', $course->id, $topic->id);
        Storage::disk('local')->makeDirectory($destinationPrefix);
        copy(__DIR__ . '/test.jpg', Storage::disk('local')->path($destinationPrefix . $file));
        $result = Markdown::convertAssetPaths("![Image] (api/images/img?path={$file})", $destinationPrefix);
        $this->assertArrayHasKey('results', $result);
        $this->assertTrue(is_array($result['results']));
        $this->assertTrue(isset($result['results'][0]) && is_array($result['results'][0]));
    }

    public function testGetImagesPathsWithoutImageApi(): void
    {
        $result = Markdown::getImagesPathsWithoutImageApi("![Image] (api/images/img?path=course/1234/topic/123/test.jpg)");

        $this->assertEquals("![Image] (course/1234/topic/123/test.jpg)", $result);
    }

    public function testSanitizePathForExport(): void
    {
        $result = Path::sanitizePathForExport("courses/1234/topic/123/test.jpg");

        $this->assertEquals('topic/123/test.jpg', $result);
    }

    public function testSanitizePathFromTopicTypeService(): void
    {
        $result = TopicTypeService::sanitizePath("course/1234/topic/123/test.jpg");

        $this->assertEquals('topic/123/test.jpg', $result);
    }

    public function testDoNotConvertExternalLink(): void
    {
        $topic = $this->topic;
        $course = $topic->lesson->course;
        $destinationPrefix = sprintf('course/%d/topic/%d/', $course->id, $topic->id);
        Storage::makeDirectory($destinationPrefix);
        $markdownLink = "![Image] (https://www.google.com/images/branding/googlelogo/2x/googlelogo_light_color_272x92dp.png)";
        $result = Markdown::convertAssetPaths($markdownLink, $destinationPrefix);
        $this->assertArrayHasKey('results', $result);
        $this->assertEmpty($result['results']);
    }

    public function testALinkToAFileOnThisOriginIsReadFromThePublicDirectoryNotOverHttp(): void
    {
        Storage::fake(config('filesystems.default'));
        $destinationPrefix = sprintf('course/%d/topic/%d/', $this->topic->lesson->course->id, $this->topic->id);
        $directory = public_path('ulams-markdown-test');
        @mkdir($directory, 0777, true);
        // a 1x1 PNG
        file_put_contents($directory . '/pixel.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        try {
            $result = Markdown::convertAssetPaths('![Pixel](' . url('ulams-markdown-test/pixel.png') . ')', $destinationPrefix);
        } finally {
            @unlink($directory . '/pixel.png');
            @rmdir($directory);
        }

        $this->assertCount(1, $result['results']);
        $this->assertStringContainsString($destinationPrefix . 'pixelpng.png', $result['value']);
        Storage::assertExists($destinationPrefix . 'pixelpng.png');
    }

    public function testALinkToAMissingFileOnThisOriginStaysAsItIsAndIsNotFetched(): void
    {
        Storage::fake(config('filesystems.default'));
        $destinationPrefix = sprintf('course/%d/topic/%d/', $this->topic->lesson->course->id, $this->topic->id);
        $markdown = '![Gone](' . url('ulams-markdown-test/missing.png') . ')';

        $result = Markdown::convertAssetPaths($markdown, $destinationPrefix);

        $this->assertSame($markdown, $result['value']);
        $this->assertSame([null], $result['results']);
        $this->assertSame([], Storage::allFiles());
    }

    public function testALinkThatClimbsOutOfThePublicDirectoryIsNotRead(): void
    {
        Storage::fake(config('filesystems.default'));
        $destinationPrefix = sprintf('course/%d/topic/%d/', $this->topic->lesson->course->id, $this->topic->id);
        $markdown = '![Env](' . url('../../.env') . ')';

        $result = Markdown::convertAssetPaths($markdown, $destinationPrefix);

        $this->assertSame($markdown, $result['value']);
        $this->assertSame([], Storage::allFiles());
    }
}
