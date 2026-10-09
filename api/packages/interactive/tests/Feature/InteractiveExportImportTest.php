<?php

namespace Ulams\Interactive\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Ulams\CoursesImportExport\UlamsCoursesImportExportServiceProvider;
use Ulams\Interactive\Import\InteractiveTopicImportStrategy;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;
use Ulams\Interactive\Tests\TestCase;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use ZipArchive;

/**
 * Interactive packages in the course export/import: the export carries the played version's files; the
 * import creates a new package from them through the normal upload checks.
 */
class InteractiveExportImportTest extends TestCase
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

    public function testACourseWithAnInteractiveTopicRoundTripsThroughExportAndImport(): void
    {
        Storage::fake('local');
        Storage::fake(config('filesystems.default'));
        $service = app(InteractivePackageServiceContract::class);
        $package = $service->create($this->upload($this->packageZip('steps')), 'Steps package', null);
        $service->addVersion($package, $this->upload($this->packageZip('steps', ['version' => '2.2.0'], ['app.js' => 'second();'])), null);

        $course = Course::factory()->create(['title' => 'Exported course']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        // pinned to version 1: the export carries what learners see
        $content = InteractiveTopic::query()->create(['value' => $package->getKey(), 'version' => 1, 'start_step' => 'middle', 'end_step' => 'last', 'display' => 'background', 'height' => 500, 'text' => '**Hi**']);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'title' => 'Topic title', 'active' => true]);
        $topic->topicable()->associate($content)->save();

        $exportService = app(ExportImportServiceContract::class);
        $zipPath = $exportService->export($course->getKey(), false);

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($zipPath));
        $folder = "topic/{$topic->getKey()}/interactive";
        $this->assertSame("console.log('steps');\n", $archive->getFromName("{$folder}/app.js"));
        $this->assertNotFalse($archive->getFromName("{$folder}/ulams-interactive.json"));
        $this->assertNotFalse($archive->getFromName("{$folder}/posters/intro.webp"));
        $exported = json_decode((string) $archive->getFromName('content.json'), true)['lessons'][0]['topics'][0]['topicable'];
        $archive->close();
        $this->assertSame($folder, $exported['interactive_folder']);
        $this->assertSame('Steps package', $exported['interactive_title']);
        $this->assertSame('background', $exported['display']);
        $this->assertArrayNotHasKey('value', $exported);

        $imported = $exportService->import(new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true));

        $importedTopic = $imported->lessons->first()->topics->first();
        $this->assertInstanceOf(InteractiveTopic::class, $importedTopic->topicable);
        $copy = InteractivePackage::query()->findOrFail($importedTopic->topicable->value);
        $this->assertNotSame($package->getKey(), $copy->getKey());
        $this->assertNotSame($package->storage_key, $copy->storage_key);
        $this->assertSame('Steps package', $copy->title);
        $this->assertSame(1, $copy->current_version);
        $this->assertSame(1, $importedTopic->topicable->version);
        $this->assertSame('middle', $importedTopic->topicable->start_step);
        $this->assertSame('last', $importedTopic->topicable->end_step);
        $this->assertSame('background', $importedTopic->topicable->display);
        $this->assertSame(500, $importedTopic->topicable->height);
        $this->assertSame('**Hi**', $importedTopic->topicable->text);
        $this->assertSame("console.log('steps');\n", Storage::disk(config('filesystems.default'))->get($copy->directory(1) . '/app.js'));
    }

    public function testAnImportWithAMissingEscapingOrInvalidFolderIsRejected(): void
    {
        Storage::fake('local');
        Storage::fake(config('filesystems.default'));
        $strategy = app(InteractiveTopicImportStrategy::class);
        $root = sys_get_temp_dir() . '/interactive-import-' . bin2hex(random_bytes(4));
        mkdir($root . '/topic/1/interactive', 0777, true);

        $this->assertNull($strategy->make($root, ['interactive_folder' => 'topic/1/interactive']));
        $this->assertNull($strategy->make($root, ['interactive_folder' => '../../etc']));
        $this->assertNull($strategy->make($root, []));

        // the same checks as an upload: a php file in the folder is refused
        copy(__DIR__ . '/../Fixtures/packages/minimal/ulams-interactive.json', $root . '/topic/1/interactive/ulams-interactive.json');
        copy(__DIR__ . '/../Fixtures/packages/minimal/index.html', $root . '/topic/1/interactive/index.html');
        file_put_contents($root . '/topic/1/interactive/evil.php', '<?php');
        try {
            $strategy->make($root, ['interactive_folder' => 'topic/1/interactive']);
            $this->fail('the php file was accepted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
        $this->assertSame(0, InteractivePackage::query()->count());

        unlink($root . '/topic/1/interactive/evil.php');
        $id = $strategy->make($root, ['interactive_folder' => 'topic/1/interactive', 'interactive_title' => 'Imported']);
        $this->assertSame('Imported', InteractivePackage::query()->findOrFail($id)->title);

        Storage::build(['driver' => 'local', 'root' => sys_get_temp_dir()])->deleteDirectory(basename($root));
    }
}
