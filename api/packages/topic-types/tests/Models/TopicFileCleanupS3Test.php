<?php

namespace Ulams\TopicTypes\Tests\Models;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\Mocks\FakeS3;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\TopicRepositoryContract;
use Ulams\TopicTypes\Models\TopicContent\PDF;
use Ulams\TopicTypes\Tests\TestCase;

class TopicFileCleanupS3Test extends TestCase
{
    use DatabaseTransactions;

    public function testDeletingAFileTopicRemovesItsObjectsFromS3(): void
    {
        $s3 = FakeS3::install();
        $course = Course::factory()->create();
        $lesson = Lesson::factory(['course_id' => $course->getKey()])->create();
        $topic = Topic::factory(['lesson_id' => $lesson->getKey()])->create();
        $pdf = PDF::factory()->create(['value' => 'x.pdf']);
        $topic->topicable()->associate($pdf)->save();
        $topic->refresh();
        $directory = $pdf->generateStoragePath();
        Storage::put($directory . '/doc.pdf', 'pdf');
        Storage::put("course/{$course->id}/topic/{$topic->id}-other/keep.txt", 'neighbour');
        $this->assertArrayHasKey($directory . '/doc.pdf', $s3->objects);

        app(TopicRepositoryContract::class)->deleteModel($topic);

        $this->assertArrayNotHasKey($directory . '/doc.pdf', $s3->objects);
        $this->assertArrayHasKey("course/{$course->id}/topic/{$topic->id}-other/keep.txt", $s3->objects);
    }
}
