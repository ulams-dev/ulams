<?php

namespace Ulams\Tutor\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypes\Models\TopicContent\RichText;
use Ulams\Tutor\Models\Chunk;
use Ulams\Tutor\Services\TutorIndexer;
use Ulams\Tutor\Tests\TestCase;

class IndexTest extends TestCase
{
    use DatabaseTransactions;

    public function testRichTextTopicsOfAnyCourseAreIndexedByHeadingAndFound(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey(), 'title' => 'Brewing']);
        $text = RichText::factory()->create(['value' => '<h2>Ratio</h2><p>Use sixty grams of coffee per litre of water.</p><h2>Grind</h2><p>Medium coarse for a French press.</p>']);
        Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true, 'title' => 'Basics', 'topicable_type' => RichText::class, 'topicable_id' => $text->getKey()]);

        $this->assertSame(2, app(TutorIndexer::class)->rebuild($course->getKey()));
        $this->assertSame(0, app(TutorIndexer::class)->ensureFresh($course->getKey()), 'unchanged content is not re-indexed');

        $labels = Chunk::query()->where('course_id', $course->getKey())->pluck('label')->all();
        $this->assertContains('Brewing › Basics › Ratio', $labels);

        $found = app(\Ulams\Tutor\Retrieval\TutorRetriever::class)->search($course->getKey(), 'how many grams of coffee per litre', 'en', 5);
        $this->assertSame('Brewing › Basics › Ratio', $found->first()->label);
        $this->assertSame([], app(\Ulams\Tutor\Retrieval\TutorRetriever::class)->search($course->getKey(), 'quantum chromodynamics', 'en', 5)->all());
    }

    public function testACourseWithoutTextTopicsHasNoIndex(): void
    {
        $course = Course::factory()->create();
        $this->assertSame(0, app(TutorIndexer::class)->rebuild($course->getKey()));
        $this->assertNull(app(TutorIndexer::class)->signature($course->getKey()));
    }
}
