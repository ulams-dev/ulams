<?php

namespace Tests\APIs;

use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypes\Database\Factories\TopicContent\Components\Cmi5AuHelper;
use EscolaLms\TopicTypes\Database\Factories\TopicContent\Components\H5PHelper;
use EscolaLms\TopicTypes\Database\Factories\TopicContent\Components\ScormScoHelper;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\Cmi5Au;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use EscolaLms\TopicTypes\Models\TopicContent\OEmbed;
use EscolaLms\TopicTypes\Models\TopicContent\RichText;
use EscolaLms\TopicTypes\Models\TopicContent\ScormSco;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use EscolaLms\TopicTypes\Tests\TestCase;
use EscolaLms\TopicTypes\Events\TopicTypeChanged;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

class TopicTypesTutorUpdateApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
        $this->course = Course::factory()->create([
            'author_id' => $this->user->id,
        ]);
        $this->lesson = Lesson::factory(['course_id' => $this->course->id])->create();
        $this->topic = Topic::factory()->create([
            'lesson_id' => $this->lesson->id,
            'json' => ['foo' => 'bar', 'bar' => 'foo'],
        ]);
    }

    public function testUpdateTopicImage(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);
        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->response = $this->withHeaders([
            'Content' => 'multipart/form-data',
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => Image::class,
                'value' => $file,
            ]
        );

        $this->response->assertStatus(200);

        $data = json_decode($this->response->getContent());

        $this->topicId = $data->data->id;
        $path = $data->data->topicable->value;

        Storage::disk('local')->assertExists('/'.$path);

        $this->assertDatabaseHas('topic_images', [
            'value' => $path,
        ]);
        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicAudio(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);
        $file = new UploadedFile(__DIR__ . '/../mocks/audio.mp3', 'audio.mp3', 'audio/mpeg', null, true);

        $this->response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\Audio',
                'value' => $file,
            ]
        );

        $this->response->assertStatus(200);

        $data = json_decode($this->response->getContent());

        $this->topicId = $data->data->id;
        $path = $data->data->topicable->value;

        Storage::disk('local')->assertExists('/'.$path);

        $this->assertDatabaseHas('topic_audios', [
            'value' => $path,
            'length' => 1410
        ]);
        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicAudioWithNewFile(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);
        $file = UploadedFile::fake()->create('avatar.mp3');

        $this->response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\Audio',
                'value' => $file,
            ]
        );

        $this->response->assertStatus(200);

        $data = json_decode($this->response->getContent());

        $path = $data->data->topicable->value;

        Storage::disk('local')->assertExists('/'.$path);

        $this->assertDatabaseHas('topic_audios', [
            'id' => $data->data->topicable->id,
            'value' => $path,
        ]);

        // ***
        // Update sending another file as value
        // ***

        $file2 = UploadedFile::fake()->create('another.mp3');

        $this->response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\Audio',
                'value' => $file2,
            ]
        );

        $this->response->assertStatus(200);

        $data = json_decode($this->response->getContent());

        $path = $data->data->topicable->value;

        Storage::disk('local')->assertExists('/'.$path);

        $this->assertDatabaseHas('topic_audios', [
            'id' => $data->data->topicable->id,
            'value' => $path,
        ]);

        // ***
        // Update sending current file path as value
        // ***

        $this->response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\Audio',
                'value' => $path,
            ]
        );

        $this->response->assertStatus(200);

        $data = json_decode($this->response->getContent());

        $path = $data->data->topicable->value;

        Storage::disk('local')->assertExists('/'.$path);

        $this->assertDatabaseHas('topic_audios', [
            'id' => $data->data->topicable->id,
            'value' => $path,
        ]);
        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicVideo(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $file = new UploadedFile(__DIR__ . '/../mocks/video.mp4', 'video.mp4', 'video/mp4', null, true);

        $this->response = $this
            ->withHeaders([
                'Content' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ])
            ->actingAs($this->user, 'api')
            ->post('/api/admin/topics/' . $this->topic->id, [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\Video',
                'value' => $file
            ])
            ->assertJsonStructure([
                'data' => [
                    'topicable' => [
                        'id',
                        'value',
                        'url',
                        'poster',
                        'poster_url',
                        'width',
                        'height',
                        'length'
                    ]
                ]
            ]);

        $this->response->assertStatus(200);

        $data = json_decode($this->response->getContent());

        $this->topicId = $data->data->id;
        $path = $data->data->topicable->value;

        Storage::disk('local')->assertExists('/'.$path);
        $this->assertEquals(240, $data->data->topicable->height);
        $this->assertEquals(240, $data->data->topicable->width);
        $this->assertEqualsWithDelta(3666, $data->data->topicable->length, 1);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicRichtext(): void
    {
        Event::fake(TopicTypeChanged::class);
        $this->response = $this->withHeaders([
            'Content' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\RichText',
                'value' => 'lorem ipsum',
            ]
        );

        $this->response->assertStatus(200);

        $data = json_decode($this->response->getContent());

        $this->topicId = $data->data->id;
        $path = $data->data->topicable->value;

        $this->assertDatabaseHas('topic_richtexts', [
            'value' => $path,
        ]);
        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicPdf(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);
        $file = UploadedFile::fake()->create('test.pdf');

        $this->response = $this->withHeaders([
            'Content' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\PDF',
                'value' => $file,
            ]
        );

        $this->response->assertStatus(200);

        $data = json_decode($this->response->getContent());

        $this->topicId = $data->data->id;
        $path = $data->data->topicable->value;

        Storage::disk('local')->assertExists('/'.$path);

        $this->assertDatabaseHas('topic_pdfs', [
            'value' => $path,
        ]);
        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicWrongClass(): void
    {
        $this->response = $this->withHeaders([
            'Content' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\RichTextAAAAAA',
                'value' => 'lorem ipsum',
            ]
        );

        $this->response->assertStatus(422);
    }

    public function testUpdateTopicWithJson(): void
    {
        Event::fake(TopicTypeChanged::class);
        $this->response = $this->withHeaders([
            'Content' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->post(
            '/api/admin/topics/'.$this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\RichText',
                'value' => 'lorem ipsum',
                'introduction' => 'asdf1',
                'summary' => 'asdf2',
                'description' => 'asdf3',
                'json' => json_encode(['foo' => 'foobar']),
            ]
        );

        $this->response->assertStatus(200);

        $data = $this->response->json();

        $this->topicId = $data['data']['id'];
        $path = $data['data']['topicable']['value'];

        $this->assertDatabaseHas('topic_richtexts', [
            'value' => $path,
        ]);
        $this->assertEquals(['foo' => 'foobar'], $data['data']['json']);
        $this->assertEquals('foobar', $data['data']['json']['foo']);
        $this->assertEquals('asdf1', $data['data']['introduction']);
        $this->assertEquals('asdf2', $data['data']['summary']);
        $this->assertEquals('asdf3', $data['data']['description']);

        $this->response = $this->withHeaders([
            'Content' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ])->actingAs($this->user, 'api')->get(
            '/api/courses/'.$this->topic->lesson->course_id.'/program'
        );

        $this->response->assertOk();
        $data = $this->response->json();

        $this->assertEquals(['foo' => 'foobar'], $data['data']['lessons'][0]['topics'][0]['json']);
        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicImageWithReusableFile(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $imagePath = "course/{$this->course->getKey()}/reusable/image.jpg";
        Storage::makeDirectory("course/{$this->course->getKey()}/reusable");
        copy(__DIR__ . '/../mocks/image.jpg', Storage::path($imagePath));

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/'.$this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => Image::class,
                'value' => $imagePath,
            ]
        )->assertStatus(200);

        $data = json_decode($this->response->getContent());
        $path = $data->data->topicable->value;

        $this->assertEquals($imagePath, $path);
        Storage::assertExists($path);

        $this->assertDatabaseHas('topic_images', [
            'value' => $imagePath,
        ]);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicAudioWithReusableFile(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $audioPath = "course/{$this->course->getKey()}/reusable/audio.mp3";
        Storage::makeDirectory("course/{$this->course->getKey()}/reusable");
        copy(__DIR__ . '/../mocks/audio.mp3', Storage::path($audioPath));

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/' . $this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => Audio::class,
                'value' => $audioPath,
            ]
        )->assertStatus(200);

        $data = json_decode($this->response->getContent());
        $path = $data->data->topicable->value;

        $this->assertEquals($audioPath, $path);
        Storage::assertExists($path);

        $this->assertDatabaseHas('topic_audios', [
            'value' => $path,
        ]);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicVideoWithReusableFile(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $videoPath = "course/{$this->course->getKey()}/reusable/video.mp4";
        $posterPath = "course/{$this->course->getKey()}/reusable/image.jpg";
        Storage::makeDirectory("course/{$this->course->getKey()}/reusable");
        copy(__DIR__ . '/../mocks/video.mp4', Storage::path($videoPath));
        copy(__DIR__ . '/../mocks/image.jpg', Storage::path($posterPath));

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/' . $this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => Video::class,
                'value' => $videoPath,
                'poster' => $posterPath,
            ]
        )->assertStatus(200);

        $data = json_decode($this->response->getContent());
        $savedVideoPath = $data->data->topicable->value;
        $savedPosterPath = $data->data->topicable->poster;

        $this->assertEquals($videoPath, $savedVideoPath);
        $this->assertEquals($posterPath, $savedPosterPath);
        Storage::assertExists($savedVideoPath);
        Storage::assertExists($savedPosterPath);

        $this->assertDatabaseHas('topic_videos', [
            'value' => $videoPath,
            'poster' => $posterPath,
        ]);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicH5PWithReusableFile(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $contentH5P = H5PHelper::createH5PContent();

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/' . $this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => H5P::class,
                'value' => $contentH5P->getKey(),
            ]
        );

        $data = json_decode($this->response->getContent());
        $contentH5PId = $data->data->topicable->value;

        $this->assertEquals($contentH5P->getKey(), $contentH5PId);

        $this->assertDatabaseHas('topic_h5ps', [
            'value' => $contentH5PId,
        ]);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicOEmbed(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/' . $this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => OEmbed::class,
                'value' => 'abc',
            ]
        );

        $data = json_decode($this->response->getContent());
        $oEmbedResponseValue = $data->data->topicable->value;

        $this->assertEquals('abc', $oEmbedResponseValue);

        $this->assertDatabaseHas('topic_oembeds', [
            'value' => 'abc',
        ]);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicRichTextNew(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/' . $this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => RichText::class,
                'value' => 'abc',
            ]
        );

        $data = json_decode($this->response->getContent());
        $richTextResponseValue = $data->data->topicable->value;

        $this->assertEquals('abc', $richTextResponseValue);

        $this->assertDatabaseHas('topic_richtexts', [
            'value' => 'abc',
        ]);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicScormSco(): void
    {
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $scormSco = ScormScoHelper::getScormSco();

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/' . $this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => ScormSco::class,
                'value' => $scormSco->getKey(),
            ]
        );

        $value = $this->response->getData()->data->topicable->value;

        $this->assertEquals($scormSco->getKey(), $value);

        $this->assertDatabaseHas('topic_scorm_scos', [
            'value' => $scormSco->getKey(),
        ]);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicCmi5Au(): void
    {
        if (!class_exists(\EscolaLms\Cmi5\EscolaLmsCmi5ServiceProvider::class)) {
            $this->markTestSkipped('Require cmi5 package');
        }
        Storage::fake('local');
        Event::fake(TopicTypeChanged::class);

        $cmi5Au = Cmi5AuHelper::getCmi5Au();

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/' . $this->topic->id,
            [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => Cmi5Au::class,
                'value' => $cmi5Au->getKey(),
            ]
        );

        $value = $this->response->getData()->data->topicable->value;

        $this->assertEquals($cmi5Au->getKey(), $value);

        $this->assertDatabaseHas('topic_cmi5_aus', [
            'value' => $cmi5Au->getKey(),
        ]);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }
}
