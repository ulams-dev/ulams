<?php

namespace Ulams\CoursesImportExport\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Ulams\CoursesImportExport\Tests\TestCase;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/**
 * images.content_urls decides what an imported rich-text image is stored as: the images API URL (default)
 * or the plain storage URL (hosted demos, where the front and admin resize at display time).
 */
class ImportContentUrlsTest extends TestCase
{
    use DatabaseTransactions;

    private function importedValue(): string
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topicable = RichText::factory()->create(['value' => "![](course/1/lesson/2/topic/3/wysiwyg/pic.png)\n"]);
        $topic = Topic::factory()->create([
            'lesson_id' => $lesson->getKey(),
            'topicable_id' => $topicable->getKey(),
            'topicable_type' => RichText::class,
        ]);

        $method = new ReflectionMethod(app(ExportImportServiceContract::class), 'updateRichTextTopicableValue');
        $method->invoke(app(ExportImportServiceContract::class), $topic, 'course/9/topic/9/wysiwyg/');

        return (string) RichText::find($topicable->getKey())->value;
    }

    public function testKeepsTheImagesApiUrlByDefault(): void
    {
        $value = $this->importedValue();

        $this->assertStringContainsString('api/images/img?path=course/9/topic/9/wysiwyg/pic.png&w=1000', $value);
    }

    public function testWritesThePlainStorageUrlWhenContentUrlsAreOriginal(): void
    {
        config(['images.content_urls' => 'original']);

        $value = $this->importedValue();

        $this->assertSame('![](' . Storage::url('course/9/topic/9/wysiwyg/pic.png') . ")\n", $value);
        $this->assertStringNotContainsString('api/images/img', $value);
    }
}
