<?php

namespace EscolaLms\Recommender\Tests;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypes\Models\TopicContent\AbstractTopicFileContent;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use EscolaLms\TopicTypes\Models\TopicContent\OEmbed;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use EscolaLms\TopicTypes\Models\TopicContent\RichText;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

trait CreatesCourse
{
    public function createCourse(): Course
    {
        /** @var Course */
        return Course::factory()->create();
    }

    public function createCourseWithStructure(): Course
    {
        /** @var Course */
        return Course::factory()
            ->has(Lesson::factory()
                ->count(5)
                ->afterCreating(function (Lesson $lesson) {
                    Topic::factory()
                        ->count(10)
                        ->afterCreating(function (Topic $topic) {
                            $factory = $this->getTopicableFactory();
                            $topic->topicable()->associate($factory->create())->save();
                        })
                        ->create(['lesson_id' => $lesson->id]);
                })

            )
            ->create();
    }

    public function createCourseWithTopicTypes(array $topicTypesClasses): Course
    {
        /** @var Course $course */
        $course = Course::factory()->create();

        $this->createLessonWithTopics($course, $topicTypesClasses);

        return $course;
    }

    public function createLessonWithTopics(Course $course, array $topicTypesClasses): Lesson
    {
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $order = 1;

        foreach ($topicTypesClasses as $topicTypeClass) {
            $topicable = $topicTypeClass::factory()->make();

            if ($topicable instanceof AbstractTopicFileContent) {
                $topicable->value = $this->getFileStoragePath('mock' . $this->fileExtension(get_class($topicable)));
            }

            if (method_exists($topicable, 'processMetadataInfo')) {
                $topicable->processMetadataInfo();
            }
            $topicable->save();

            $topic = Topic::factory()->create(['lesson_id' => $lesson->id, 'order' => $order++]);
            $topic->topicable()->associate($topicable)->save();
        }

        return $lesson;
    }

    private function getTopicableFactory(): Factory
    {
        $classes = [
            RichText::factory(),
            Video::factory(),
            Image::factory(),
            OEmbed::factory(),
            PDF::factory(),
            H5P::factory()
        ];

        return $classes[array_rand($classes)];
    }

    private function fileExtension(string $topicTypeClass): string
    {
        $extensions = [
            Video::class => '.mp4',
            Audio::class => '.mp3',
            Image::class => '.jpg',
            PDF::class => '.pdf',
            H5P::class => '.h5p',
        ];

        return $extensions[$topicTypeClass];
    }

    private function getFileStoragePath(string $fileName): string
    {
        $filePath = __DIR__ . '/mocks/' . $fileName;
        $storagePath = Storage::path($fileName);

        copy($filePath, $storagePath);

        return $fileName;
    }
}
