<?php

namespace EscolaLms\CoursesImportExport\Tests\APIs;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder;
use EscolaLms\CoursesImportExport\Models\Course as CourseImportExport;
use EscolaLms\CoursesImportExport\Tests\TestCase;
use EscolaLms\Tags\Models\Tag;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use EscolaLms\TopicTypes\Models\TopicContent\RichText;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CourseExportAdminApiTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    /**
     * @test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesExportImportPermissionSeeder::class);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');

        Storage::fake(config('filesystems.default'));

        Storage::put('dummy.mp4', 'Some dummy data');
        Storage::put('dummy.mp3', 'Some dummy data');
        Storage::put('dummy.jpg', 'Some dummy data');
        Storage::put('dummy.png', 'Some dummy data');
        Storage::put('dummy.pdf', 'Some dummy data');

        $course = Course::factory()
            ->has(
                Category::factory()
                    ->count(1)
                    ->state(fn () => ['parent_id' => null])
            )
            ->has(Tag::factory()->count(2))
            ->create([
                'author_id' => $this->user->id,
            ]);
        $lesson = Lesson::factory()->create([
            'course_id' => $course->id,
        ]);
        $topic_audio = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_image = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_pdf = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_video = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);

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

        $topic_audio->topicable()->associate($topicable_audio)->save();
        $topic_image->topicable()->associate($topicable_image)->save();
        $topic_pdf->topicable()->associate($topicable_pdf)->save();
        $topic_video->topicable()->associate($topicable_video)->save();

        $this->course = $course;
    }

    /**
     * @test
     */
    public function testExportCreated()
    {
        $id = $this->course->id;

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/courses/' . $id . '/export/'
        );

        $this->response->assertOk();

        $data = $this->response->getData();

        $filename = basename($data->data);

        $filepath = sprintf('exports/courses/%d/%s', $id, $filename);

        Storage::assertExists($filepath);
    }

    public function testExportRichText()
    {
        $course = Course::factory()->create();
        $courseImportExport = new CourseImportExport($course->toArray());
        $courseImportExport->save();

        $lesson = Lesson::factory()->create([
            'course_id' => $courseImportExport->getKey(),
            'summary' => 'test',
        ]);

        $image1 = UploadedFile::fake()->image('test.png');
        Storage::putFileAs("course/{$course->getKey()}/lesson/{$lesson->getKey()}/topic/99999/wysiwyg", $image1, 'test.png');
        $image2 = UploadedFile::fake()->image('test2.png');
        Storage::putFileAs("course/{$course->getKey()}/lesson/{$lesson->getKey()}/topic/99999/wysiwyg", $image2, 'test2.png');
        $pdf = UploadedFile::fake()->create('sample.pdf');
        Storage::putFileAs("course/{$course->getKey()}/lesson/{$lesson->getKey()}/topic/99999/wysiwyg", $pdf, 'sample.pdf');
        $url = url('');

        $value = "
        ![]($url/api/images/img?path=course/{$course->getKey()}/lesson/{$lesson->getKey()}/topic/99999/wysiwyg/test.png&w=1000)\r\n\r\n
        ![]($url/api/images/img?path=course/{$course->getKey()}/lesson/{$lesson->getKey()}/topic/99999/wysiwyg/test2.png&w=1000)\r\n\r\n
        ![$url//storage/course/{$course->getKey()}/lesson/{$lesson->getKey()}/topic/99999/wysiwyg/sample.pdf]($url//storage/course/{$course->getKey()}/lesson/{$lesson->getKey()}/topic/99999/wysiwyg/sample.pdf)\r\n\r\n\\";

        $topicable = RichText::factory()->create([
            'value' => $value,
        ]);

        Topic::factory()->create([
            'lesson_id' => $lesson->id,
            'topicable_id' => $topicable->id,
            'topicable_type' => RichText::class,
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            "/api/admin/courses/{$courseImportExport->getKey()}/export/"
        );

        $filename = basename($this->response->getData()->data);
        $filepath = sprintf('exports/courses/%d/%s', $courseImportExport->getKey(), $filename);
        Storage::assertExists($filepath);
    }
}
