<?php

namespace Tests\Helpers;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypes\Facades\Markdown;
use EscolaLms\TopicTypes\Facades\Path;
use EscolaLms\TopicTypes\Services\TopicTypeService;
use EscolaLms\TopicTypes\Tests\TestCase;
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
}
