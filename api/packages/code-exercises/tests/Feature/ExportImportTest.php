<?php

namespace Ulams\CodeExercises\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\CodeExercises\Tests\TestCase;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Ulams\CoursesImportExport\UlamsCoursesImportExportServiceProvider;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use ZipArchive;

/**
 * An exercise is plain JSON, so the course export carries it and the import creates the topic again through
 * the topic API, which validates the files a second time (an export is untrusted input).
 */
class ExportImportTest extends TestCase
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

    public function testACourseWithAnExerciseRoundTripsThroughExportAndImport(): void
    {
        Storage::fake('local');
        Storage::fake(config('filesystems.default'));
        $course = Course::factory()->create(['title' => 'Exported course']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $content = CodeExerciseTopic::query()->create($this->exercise(['timeout_ms' => 9000, 'completion_rule' => 'on_attempt', 'pass_score' => 70]));
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'title' => 'Exercise title', 'active' => true]);
        $topic->topicable()->associate($content)->save();

        $service = app(ExportImportServiceContract::class);
        $zipPath = $service->export($course->getKey(), false);

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($zipPath));
        $exported = json_decode((string) $archive->getFromName('content.json'), true)['lessons'][0]['topics'][0]['topicable'];
        $archive->close();
        $this->assertSame($this->starterFiles(), $exported['files']);
        $this->assertSame($this->visibleTests(), $exported['visible_tests']);
        $this->assertSame(9000, $exported['timeout_ms']);

        $imported = $service->import(new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true));

        $copy = $imported->lessons->first()->topics->first()->topicable;
        $this->assertInstanceOf(CodeExerciseTopic::class, $copy);
        $this->assertNotSame($content->getKey(), $copy->getKey());
        $this->assertSame($this->starterFiles(), $copy->files);
        $this->assertSame($this->visibleTests(), $copy->visible_tests);
        $this->assertSame('on_attempt', $copy->completion_rule);
        $this->assertSame(70, $copy->pass_score);
        $this->assertSame(9000, $copy->timeout_ms);
        $this->assertSame('browser', $copy->runtime);
        $this->assertSame('practice', $copy->grading);
    }

    public function testAnImportedExerciseIsValidatedAgainAndAHostileOneIsRefused(): void
    {
        Storage::fake('local');
        Storage::fake(config('filesystems.default'));
        $course = Course::factory()->create(['title' => 'Exported course']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'title' => 'Exercise title', 'active' => true]);
        $topic->topicable()->associate(CodeExerciseTopic::query()->create($this->exercise()))->save();
        $service = app(ExportImportServiceContract::class);
        $zipPath = $service->export($course->getKey(), false);

        // the same archive with a file that climbs out of the exercise and a PHP file
        $archive = new ZipArchive();
        $this->assertTrue($archive->open($zipPath));
        $content = json_decode((string) $archive->getFromName('content.json'), true);
        $content['lessons'][0]['topics'][0]['topicable']['files'] = [['path' => '../../public/shell.php', 'content' => '<?php system($_GET["c"]);']];
        $archive->addFromString('content.json', (string) json_encode($content));
        $archive->close();

        $before = CodeExerciseTopic::query()->count();
        try {
            $service->import(new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true));
        } catch (\Throwable) {
            // refused with an error: nothing was created
        }

        foreach (CodeExerciseTopic::query()->get() as $created) {
            foreach ($created->files as $file) {
                $this->assertStringNotContainsString('..', $file['path'], 'a hostile path must never be stored');
                $this->assertStringEndsNotWith('.php', $file['path']);
            }
        }
        $this->assertLessThanOrEqual($before, CodeExerciseTopic::query()->count(), 'no topic is created from the hostile file list');
    }
}
