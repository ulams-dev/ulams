<?php

namespace Ulams\Courses\Database\Seeders;

use Ulams\Core\Enums\UserRole;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Models\User;
use Ulams\Courses\Repositories\CourseProgressRepository;
use Ulams\Courses\Services\ProgressService;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class ProgressSeeder extends Seeder
{
    public function run()
    {
        if (Course::count() === 0) {
            $this->call(CoursesSeeder::class);
        }

        /** @var Collection $students */
        $students = User::role(UserRole::STUDENT)->whereHas('courses')->take(10)->get();
        if ($students->isEmpty()) {
            $students = User::role(UserRole::STUDENT)->take(10)->get();
            if ($students->isEmpty()) {
                $students = User::factory()->count(10)->create();
                foreach ($students as $student) {
                    $student->assignRole(UserRole::STUDENT);
                }
            }
            foreach ($students as $student) {
                /** @var User $student */
                $student->courses()->syncWithoutDetaching([Course::inRandomOrder()->first()->getKey()]);
            }
        }

        /** @var ProgressService $progressService */
        $progressService = app(ProgressService::class);
        /** @var CourseProgressRepository $progressRepository */
        $progressRepository = app(CourseProgressRepository::class);

        /** @var User $student */
        foreach ($students as $student) {
            $progresses = $progressService->getByUser($student);
            /** @var CourseProgressCollection $courseProgress */
            foreach ($progresses as $courseProgress) {
                $course = $courseProgress->getCourse();
                foreach ($course->topics as $topic) {
                    /** @var Topic $topic */
                    $status = ProgressStatus::getRandomValue();
                    $progressRepository->updateInTopic($topic, $student, $status, $status !== ProgressStatus::INCOMPLETE ? rand(60, 300) : null);
                    if ($status === ProgressStatus::IN_PROGRESS) {
                        $progressService->ping($student, $topic);
                    }
                }
                $progressService->update($course, $student, []);
            }
        }
    }
}
