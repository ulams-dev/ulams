<?php

namespace EscolaLms\Recommender\Tests\Feature;

use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Recommender\Exceptions\RecommenderDisabledException;
use EscolaLms\Recommender\Services\Contracts\RecommenderServiceContract;
use EscolaLms\Recommender\Tests\CreatesCourse;
use EscolaLms\Recommender\Tests\TestCase;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use EscolaLms\TopicTypes\Models\TopicContent\OEmbed;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use EscolaLms\TopicTypes\Models\TopicContent\RichText;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use Illuminate\Support\Facades\Config;

class RecommenderServiceTest extends TestCase
{
    use CreatesCourse;

    private RecommenderServiceContract $recommenderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recommenderService = app()->make(RecommenderServiceContract::class);

        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.course_model', '{"model": "course"}');
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.exercise_model', '{"model": "exercise"}');
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.enabled', true);
    }

    public function testCourseMakeData(): void
    {
        $course = $this->createCourseWithTopicTypes([
            PDF::class,
            OEmbed::class,
            Video::class,
            RichText::class,
            Image::class,
            PDF::class,
            Video::class,
        ]);

        $result = $this->recommenderService->makeCourseData($course->getKey());

        $this->assertEquals(2, $result['PDF']);
        $this->assertEquals(1, $result['OEmbed']);
        $this->assertEquals(2, $result['Video']);
        $this->assertEquals(1, $result['RichText']);
        $this->assertEquals(1, $result['Image']);
        $this->assertArrayHasKey('PDF_length', $result);
        $this->assertArrayHasKey('PDF_page', $result);
        $this->assertArrayHasKey('Video_length', $result);
        $this->assertArrayHasKey('RichText_length', $result);
        $this->assertArrayNotHasKey('Image_length', $result);
        $this->assertArrayNotHasKey('OEmbed_length', $result);
    }

    public function testCourseMakeDataModelNotSet(): void
    {
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.course_model');

        $course = $this->createCourseWithTopicTypes([
            PDF::class,
            OEmbed::class,
            Video::class,
            RichText::class,
            Image::class,
            PDF::class,
            Video::class,
        ]);

        $this->expectException(RecommenderDisabledException::class);
        $this->expectExceptionMessage('Recommender course model is not set!');

        $this->recommenderService->makeCourseData($course->getKey());
    }

    public function testTopicMakeData(): void
    {
        $lesson1TopicTypes = [PDF::class, OEmbed::class, Video::class, RichText::class, Image::class, PDF::class, Video::class];
        $lesson2TopicTypes = [OEmbed::class, RichText::class, Video::class, OEmbed::class];
        $course = $this->createCourse();
        $lesson1 = $this->createLessonWithTopics($course, $lesson1TopicTypes);
        $lesson2 = $this->createLessonWithTopics($course, $lesson2TopicTypes);

        $result = $this->recommenderService->makeTopicData($lesson1->getKey());

        $this->assertEquals([
            'question_number' => count($lesson1TopicTypes) + count($lesson2TopicTypes) + 1,
            'Video_1' => 1.0,
            'PDF_2' => 1.0,
            'Image_3' => 1.0,
            'RichText_4' => 1.0,
            'Video_5' => 1.0,
        ], $result);

        $result = $this->recommenderService->makeTopicData($lesson2->getKey());

        $this->assertEquals([
            'question_number' => count($lesson1TopicTypes) + count($lesson2TopicTypes) + 1,
            'OEmbed_1' => 1.0,
            'Video_2' => 1.0,
            'RichText_3' => 1.0,
            'OEmbed_4' => 1.0,
        ], $result);
    }

    public function testTopicMakeDataModelNotSet(): void
    {
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.exercise_model');

        $course = $this->createCourse();
        $lesson1 = $this->createLessonWithTopics($course, [PDF::class, OEmbed::class, Video::class, RichText::class, Image::class, PDF::class, Video::class]);

        $this->expectException(RecommenderDisabledException::class);
        $this->expectExceptionMessage('Recommender exercise model is not set!');

        $this->recommenderService->makeTopicData($lesson1->getKey());
    }
}
