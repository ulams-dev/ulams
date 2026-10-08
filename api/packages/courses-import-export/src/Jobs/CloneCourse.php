<?php

namespace Ulams\CoursesImportExport\Jobs;

use Ulams\Courses\Models\Course;
use Ulams\CoursesImportExport\Events\CloneCourseFailedEvent;
use Ulams\CoursesImportExport\Events\CloneCourseFinishedEvent;
use Ulams\CoursesImportExport\Events\CloneCourseStartedEvent;
use Ulams\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CloneCourse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 18000;

    private Course $course;
    private Authenticatable $user;

    /**
     * @param Course $course
     * @param Authenticatable $user
     */
    public function __construct(Course $course, Authenticatable $user)
    {
        $this->course = $course;
        $this->user = $user;
    }

    public function handle(ExportImportServiceContract $exportImportService): bool
    {
        CloneCourseStartedEvent::dispatch($this->user, $this->course);
        Log::info('Course cloning started');

        try {
            $fileDir = $exportImportService->export($this->course->getKey(), false);
            $file = $this->createFileToExport($fileDir);

            /** @var Course $course */
            $course = $exportImportService->import($file);
            $course->authors()->sync($this->course->authors()->pluck('author_id'));

            CloneCourseFinishedEvent::dispatch($this->user, $course);
            Log::info('Course cloning finished');

            return true;

        } catch (Exception $exception) {
            CloneCourseFailedEvent::dispatch($this->user, $this->course);
            Log::error('[' . self::class . '] ' . $exception->getMessage());
        }

        return false;
    }

    private function createFileToExport(string $fileDir): UploadedFile
    {
        return new UploadedFile(
            $fileDir,
            'export.zip',
            null,
            null,
            true
        );
    }
}
