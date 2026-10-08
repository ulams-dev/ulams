<?php

namespace Tests\Commands;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use EscolaLms\TopicTypes\Models\TopicContent\RichText;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use EscolaLms\TopicTypes\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class FillTopicTypeMetadataCommandTest extends TestCase
{
    use WithFaker, DatabaseTransactions;

    public function testFillTopicTypeMetadataCommand(): void
    {
        Storage::fake('local');

        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create([
            'course_id' => $course->id,
        ]);
        $topic_audio = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_video = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_pdf = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_richtext = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);

        $topicable_audio = Audio::factory()->create([
            'value' => $this->getFileStoragePath('audio.mp3'),
            'length' => 0
        ]);
        $topicable_video = Video::factory()->create([
            'value' => $this->getFileStoragePath('video.mp4'),
            'height' => null,
            'width' => null,
            'length' => null
        ]);
        $topicable_pdf = PDF::factory()->create([
            'value' => $this->getFileStoragePath('pdf.pdf')
        ]);
        $topicable_richtext = RichText::factory()->create([
            'value' => 'lorem ipsum',
            'length' => null
        ]);

        $topic_audio->topicable()->associate($topicable_audio)->save();
        $topic_video->topicable()->associate($topicable_video)->save();
        $topic_pdf->topicable()->associate($topicable_pdf)->save();
        $topic_richtext->topicable()->associate($topicable_richtext)->save();

        $this->artisan('escolalms:fill-topic-types-metadata audio')->assertSuccessful();
        $this->artisan('escolalms:fill-topic-types-metadata video')->assertSuccessful();
        $this->artisan('escolalms:fill-topic-types-metadata pdf')->assertSuccessful();
        $this->artisan('escolalms:fill-topic-types-metadata richText')->assertSuccessful();

        $topicable_audio->refresh();
        $topicable_video->refresh();
        $topicable_pdf->refresh();
        $topicable_richtext->refresh();

        // audio
        $this->assertNotNull($topicable_audio->length);
        $this->assertEquals(1410, $topicable_audio->length);

        // video
        $this->assertNotNull($topicable_video->width);
        $this->assertNotNull($topicable_video->height);
        $this->assertNotNull($topicable_video->length);
        $this->assertEquals(240, $topicable_video->width);
        $this->assertEquals(240, $topicable_video->height);
        $this->assertEqualsWithDelta(3666, $topicable_video->length, 1);

        // pdf
        $this->assertNotNull($topicable_pdf->length);
        $this->assertNotNull($topicable_pdf->page_count);
        $this->assertEquals(949, $topicable_pdf->length);
        $this->assertEquals(2, $topicable_pdf->page_count);

        // rich text
        $this->assertNotNull($topicable_richtext->length);
        $this->assertEquals(11, $topicable_richtext->length);
    }

    public function testFillTopicTypeMetadataCommandModelNotFound(): void
    {
        $this
            ->artisan('escolalms:fill-topic-types-metadata testModel')
            ->expectsOutput('Model TestModel does not exist')
            ->assertFailed();
    }

    private function getFileStoragePath(string $fileName): string
    {
        $filePath = __DIR__ . '/../mocks/' . $fileName;
        $storagePath = Storage::path($fileName);

        copy($filePath, $storagePath);

        return $fileName;
    }
}
