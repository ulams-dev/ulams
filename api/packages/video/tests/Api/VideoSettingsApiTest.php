<?php

namespace EscolaLms\Video\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Video\Tests\TestCase;
use EscolaLms\Video\Tests\VideoTesting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

class VideoSettingsApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, VideoTesting;


    public function testVideoPackageEnableExtendableResource(): void
    {
        Config::set('escolalms_video.enable', true);

        $course = $this->createCourse();

        $this->actingAs($this->makeAdmin())
            ->getJson('/api/courses/' . $course->getKey() . '/program')
            ->assertOk()
            ->assertJson($this->assertCourseResponse(fn($json) => $json
                ->has('topicable', fn($json) => $json->has('url')->has('value')->has('hls')->has('hls_url')->etc())
                ->etc()
            ));
    }

    public function testVideoPackageDisableExtendableResource(): void
    {
        Config::set('escolalms_video.enable', false);

        $course = $this->createCourse();

        $this->actingAs($this->makeAdmin())
            ->getJson('/api/courses/' . $course->getKey() . '/program')
            ->assertOk()
            ->assertJson($this->assertCourseResponse(fn($json) => $json
                ->has('topicable', fn($json) => $json->has('url')->has('value')->missing('hls')->missing('hls_url')->etc())
                ->etc()
            ));
    }

    public function testVideoPackageNonStrictValueExtendableResource(): void
    {
        Config::set('escolalms_video.non_strict_value', true);

        $student = $this->makeStudent();

        /** @var Course $course */
        $course = $this->createCourse();
        $course->users()->attach($student->getKey());

        $this->actingAs($student, 'api')
            ->getJson('/api/courses/' . $course->getKey() . '/program')
            ->assertOk()
            ->assertJson($this->assertCourseResponse(fn($json) => $json
                ->has('topicable', fn(AssertableJson $json) => $json
                    ->has('url')
                    ->has('value')
                    ->where('url', Storage::url('video.mp4'))
                    ->where('value', 'video.mp4')
                    ->etc()
                )->etc()
            ));
    }

    public function testVideoPackageNonStrictValueFalseExtendableResource(): void
    {
        Config::set('escolalms_video.non_strict_value', false);

        $student = $this->makeStudent();

        /** @var Course $course */
        $course = $this->createCourse();
        $course->users()->attach($student->getKey());

        $this->actingAs($student, 'api')
            ->getJson('/api/courses/' . $course->getKey() . '/program')
            ->assertOk()
            ->assertJson($this->assertCourseResponse(fn($json) => $json
                ->has('topicable', fn(AssertableJson $json) => $json
                    ->has('url')
                    ->has('value')
                    ->where('url', null)
                    ->where('value', null)
                    ->etc()
                )->etc()
            ));
    }

    private function assertCourseResponse(callable $topicableJson): callable
    {
        return fn(AssertableJson $json) => $json
            ->has('data', fn($json) => $json
                ->has('lessons', fn($json) => $json
                    ->each(fn($json) => $json
                        ->has('topics', fn($json) => $json
                            ->each($topicableJson)
                        )->etc()
                    )
                )->etc()
            )->etc();
    }
}
