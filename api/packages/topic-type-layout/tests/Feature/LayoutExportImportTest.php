<?php

namespace Ulams\TopicTypeLayout\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Ulams\CoursesImportExport\UlamsCoursesImportExportServiceProvider;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use Ulams\TopicTypeLayout\Models\LayoutTopic;
use Ulams\TopicTypeLayout\Tests\TestCase;
use ZipArchive;

/**
 * A layout is plain JSON, so the course export carries the document and the fallback, and the import
 * creates the topic again through the topic API, which validates the document a second time.
 */
class LayoutExportImportTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsCategoriesServiceProvider::class,
            UlamsScormServiceProvider::class,
            UlamsTagsServiceProvider::class,
            UlamsCoursesImportExportServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ulams.tags.ignore_migrations', false);
    }

    public function testACourseWithALayoutTopicRoundTripsThroughExportAndImport(): void
    {
        Storage::fake('local');
        Storage::fake(config('filesystems.default'));
        $document = [
            ['component' => 'Timeline', 'props' => $this->example('Timeline')['props']],
            ['component' => 'FlipCards', 'props' => $this->example('FlipCards')['props'], 'id' => 'cards'],
        ];
        $course = Course::factory()->create(['title' => 'Exported course']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $content = LayoutTopic::query()->create(['document' => $document, 'markdown_fallback' => "## Fallback\n\nText"]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'title' => 'Layout title', 'active' => true]);
        $topic->topicable()->associate($content)->save();

        $service = app(ExportImportServiceContract::class);
        $zipPath = $service->export($course->getKey(), false);

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($zipPath));
        $exported = json_decode((string) $archive->getFromName('content.json'), true)['lessons'][0]['topics'][0]['topicable'];
        $archive->close();
        $this->assertSame($document, $exported['document']);
        $this->assertSame("## Fallback\n\nText", $exported['markdown_fallback']);
        $this->assertSame('1', $exported['schema_version']);

        $imported = $service->import(new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true));

        $copy = $imported->lessons->first()->topics->first()->topicable;
        $this->assertInstanceOf(LayoutTopic::class, $copy);
        $this->assertNotSame($content->getKey(), $copy->getKey());
        $this->assertSame($document, $copy->document);
        $this->assertSame("## Fallback\n\nText", $copy->markdown_fallback);
    }
}
