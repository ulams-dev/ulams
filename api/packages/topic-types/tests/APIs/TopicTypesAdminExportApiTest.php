<?php

namespace Tests\APIs;

use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypes\Database\Factories\TopicContent\Components\H5PHelper;
use Ulams\TopicTypes\Models\TopicContent\Audio;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\TopicTypes\Models\TopicContent\OEmbed;
use Ulams\TopicTypes\Models\TopicContent\PDF;
use Ulams\TopicTypes\Models\TopicContent\RichText;
use Ulams\TopicTypes\Models\TopicContent\Video;
use Ulams\TopicTypes\Tests\TestCase;
use Ulams\TopicTypes\Events\TopicTypeChanged;
use Ulams\TopicTypes\Models\TopicContent\Image;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

class TopicTypesAdminExportApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\Ulams\CoursesImportExport\UlamsCoursesImportExportServiceProvider::class)) {
            $this->markTestSkipped('Course Import Export package not installed');
        }

        $this->seed(CoursesPermissionSeeder::class);
        $this->seed(CoursesExportImportPermissionSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->course = Course::factory()->create([
            'author_id' => $this->user->id,
        ]);
        $this->lesson = Lesson::factory(['course_id' => $this->course->id])->create();
        $this->topic = Topic::factory()->create([
            'lesson_id' => $this->lesson->id,
            'json' => ['foo' => 'bar', 'bar' => 'foo'],
        ]);

        Storage::fake(config('filesystems.default'));
        Storage::put('dummy.mp4', 'Some dummy data');
        Storage::put('dummy.mp3', 'Some dummy data');
        Storage::put('dummy.jpg', 'Some dummy data');
        Storage::put('dummy.png', 'Some dummy data');
        Storage::put('dummy.pdf', 'Some dummy data');
        $this->h5p = H5PHelper::createH5PContent();

        $lesson = Lesson::factory()->create([
            'course_id' => $this->course->id,
        ]);

        $prepareDataArray = ['audio' => null, 'image' => null, 'pdf' => null, 'video' => null, 'richtext' => null, 'oembed' => null, 'h5p' => null];
        foreach ($prepareDataArray as &$type) {
            $type = Topic::factory()->create([
                'lesson_id' => $lesson->id,
            ]);
        }

        $topicable_audio = Audio::factory()->create([
            'value' => 'dummy.mp3',
        ]);
        $topicable_image = Image::factory()->create([
            'value' => 'dummy.jpg',
        ]);
        $topicable_pdf = PDF::factory()->create([
            'value' => 'dummy.pdf',
        ]);
        $topicable_video = Video::factory()->create([
            'value' => 'dummy.mp4',
            'poster' => 'dummy.png',
        ]);
        $topicable_richtext = RichText::factory()->create();
        $topicable_oembed = OEmbed::factory()->create();
        $topicable_h5p = H5P::factory()->create([
            'value' => $this->h5p->id
        ]);

        $prepareDataArray['audio']->topicable()->associate($topicable_audio)->save();
        $prepareDataArray['image']->topicable()->associate($topicable_image)->save();
        $prepareDataArray['pdf']->topicable()->associate($topicable_pdf)->save();
        $prepareDataArray['video']->topicable()->associate($topicable_video)->save();
        $prepareDataArray['richtext']->topicable()->associate($topicable_richtext)->save();
        $prepareDataArray['oembed']->topicable()->associate($topicable_oembed)->save();
        $prepareDataArray['h5p']->topicable()->associate($topicable_h5p)->save();
    }

    public function testExportTopic(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $this->response = $this->actingAs($this->user, 'api')->get(
            '/api/admin/courses/' . $this->course->getKey() . '/export'
        );

        $this->response->assertStatus(200);
    }
}
