<?php

namespace Ulams\LiaScript\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Ulams\CoursesImportExport\UlamsCoursesImportExportServiceProvider;
use Ulams\LiaScript\Models\LiaScriptDocument;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\LiaScript\Services\LiaScriptService;
use Ulams\LiaScript\Tests\TestCase;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use ZipArchive;

/**
 * LiaScript in the course export/import (packages/courses-import-export): the export carries the
 * current course text and assets; the import creates a new document from them.
 */
class LiaScriptCourseExportTest extends TestCase
{
    private const COURSE = "# Git\n\n## Commits\n\n![diagram](img/x.png)\n";

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

    public function testACourseWithALiaScriptTopicRoundTripsThroughExportAndImport(): void
    {
        Storage::fake('local');
        Storage::fake(config('filesystems.default'));
        $service = app(LiaScriptService::class);

        $zip = $this->makeZip(['README.md' => self::COURSE, 'img/x.png' => 'PNG-BYTES']);
        $document = $service->create('Git basics', null, new UploadedFile($zip, 'c.zip', null, null, true), null);
        $service->update($document, null, self::COURSE . "\n## Branches\n", null, 'more', null);

        $course = Course::factory()->create(['title' => 'Exported course']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $content = LiaScriptTopic::query()->create(['value' => $document->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'title' => 'Topic title', 'active' => true]);
        $topic->topicable()->associate($content)->save();

        $exportService = app(ExportImportServiceContract::class);
        $zipPath = $exportService->export($course->getKey(), false);

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($zipPath));
        $folder = "topic/{$topic->getKey()}/liascript";
        $this->assertSame(self::COURSE . "\n## Branches\n", $archive->getFromName("{$folder}/README.md"));
        $this->assertSame('PNG-BYTES', $archive->getFromName("{$folder}/img/x.png"));
        $exported = json_decode((string) $archive->getFromName('content.json'), true);
        $archive->close();
        $exportedTopic = $exported['lessons'][0]['topics'][0];
        $this->assertSame('Topic title', $exportedTopic['title']);
        $this->assertSame($folder, $exportedTopic['topicable']['liascript_folder']);
        $this->assertSame('Git basics', $exportedTopic['topicable']['liascript_title']);
        $this->assertArrayNotHasKey('value', $exportedTopic['topicable']);

        $imported = $exportService->import(new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true));

        $importedTopic = $imported->lessons->first()->topics->first();
        $this->assertSame('Topic title', $importedTopic->title);
        $this->assertInstanceOf(LiaScriptTopic::class, $importedTopic->topicable);
        $copy = LiaScriptDocument::query()->findOrFail($importedTopic->topicable->value);
        $this->assertNotSame($document->getKey(), $copy->getKey());
        $this->assertSame('Git basics', $copy->title);
        $version = $copy->version($copy->current_version);
        $this->assertSame(self::COURSE . "\n## Branches\n", $version->markdown);
        $this->assertSame(['img/x.png'], array_keys($version->assets));
        $this->assertSame('PNG-BYTES', Storage::disk(config('filesystems.default'))->get($version->assets['img/x.png']['path']));
    }

    public function testAnImportWithAMissingOrEscapingFolderIsRejected(): void
    {
        Storage::fake('local');
        Storage::fake(config('filesystems.default'));
        $strategy = new \Ulams\LiaScript\Import\LiaScriptTopicImportStrategy(app(LiaScriptService::class));
        $root = sys_get_temp_dir() . '/lia-import-' . bin2hex(random_bytes(4));
        mkdir($root . '/topic/1/liascript', 0777, true);

        $this->assertNull($strategy->make($root, ['liascript_folder' => 'topic/1/liascript']));
        $this->assertNull($strategy->make($root, ['liascript_folder' => '../../etc']));
        $this->assertNull($strategy->make($root, []));

        file_put_contents($root . '/topic/1/liascript/README.md', "# Imported\n");
        $id = $strategy->make($root, ['liascript_folder' => 'topic/1/liascript']);
        $this->assertSame('Imported', LiaScriptDocument::query()->findOrFail($id)->title);

        Storage::build(['driver' => 'local', 'root' => sys_get_temp_dir()])->deleteDirectory(basename($root));
    }
}
