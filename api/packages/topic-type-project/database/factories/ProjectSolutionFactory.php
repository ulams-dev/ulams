<?php

namespace EscolaLms\TopicTypeProject\Database\Factories;

use EscolaLms\Auth\Models\User;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
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
