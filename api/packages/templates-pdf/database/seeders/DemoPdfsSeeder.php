<?php

namespace Ulams\TemplatesPdf\Database\Seeders;

use Ulams\Core\Enums\UserRole;
use Ulams\Courses\Database\Seeders\ProgressSeeder;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\User;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Courses\Services\Contracts\ProgressServiceContract;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Facades\Template;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class DemoPdfsSeeder extends Seeder
{
    protected ProgressServiceContract $progressService;
    protected CourseProgressRepositoryContract $progressRepository;

    public function __construct()
    {
        $this->progressService = app(ProgressServiceContract::class);
        $this->progressRepository = app(CourseProgressRepositoryContract::class);
    }

    public function run()
    {
        if (!class_exists(\Ulams\Courses\UlamsCourseServiceProvider::class)) {
            return;
        }

        Event::fake(CourseFinished::class);

        $students = $this->getStudents();

        /** @var User&Authenticatable $student */
        foreach ($students as $student) {
            $progresses = $this->progressService->getByUser($student);

            if ($progresses->count() > 0) {
                /** @var CourseProgressCollection $courseProgress */
                $courseProgress = $progresses->first();

                $this->ensureCourseIsFinished($courseProgress);

                Template::handleEvent(new EventWrapper(new CourseFinished($student, $courseProgress->getCourse())));
            }
        }
    }

    protected function getStudents(): Collection
    {
        $students = User::role(UserRole::STUDENT)->whereHas('courses')->inRandomOrder()->take(5)->get();
        if ($students->isEmpty() && class_exists(\Ulams\TopicTypes\UlamsTopicTypesServiceProvider::class)) {
            $this->call(ProgressSeeder::class);
            $students = User::role(UserRole::STUDENT)->whereHas('courses')->inRandomOrder()->take(5)->get();
        }
        return $students;
    }

    protected function ensureCourseIsFinished(CourseProgressCollection $courseProgress): void
    {
        if (!$courseProgress->isFinished()) {
            $course = $courseProgress->getCourse();
            foreach ($course->topics as $topic) {
                $this->progressRepository->updateInTopic($topic, $courseProgress->getUser(), ProgressStatus::COMPLETE, rand(60, 300));
            }
            $this->progressService->update($course, $courseProgress->getUser(), []);
        }
    }
}
