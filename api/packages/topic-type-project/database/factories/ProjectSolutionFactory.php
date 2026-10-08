<?php

namespace Ulams\TopicTypeProject\Database\Factories;

use Ulams\Auth\Models\User;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectSolutionFactory extends Factory
{
    protected $model = ProjectSolution::class;

    public function definition(): array
    {
        $course = Course::factory()->state(['status' => CourseStatusEnum::PUBLISHED])->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();

        return [
            'path' => $this->faker->filePath(),
            'user_id' => User::factory(),
            'topic_id' => Topic::factory()->state(['lesson_id' => $lesson->getKey()]),
        ];
    }
}
