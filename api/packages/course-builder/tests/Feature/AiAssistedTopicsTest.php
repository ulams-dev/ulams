<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Services\AiAssistedTopics;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;

/** AI content labelling (4.3): the program marks the topics that were applied from a builder session. */
class AiAssistedTopicsTest extends TestCase
{
    /** @return array<int,array<string,mixed>> every topic object of a program response, nested lessons included */
    private function topicsIn(array $node): array
    {
        $found = [];
        foreach ($node as $value) {
            if (is_array($value)) {
                if (array_key_exists('topicable_type', $value) && array_key_exists('lesson_id', $value)) {
                    $found[] = $value;
                } else {
                    $found = array_merge($found, $this->topicsIn($value));
                }
            }
        }

        return $found;
    }

    private function appliedCourse(): Course
    {
        $session = $this->toApplied($this->author(), 'coffee-brewing.md', ['quiz', 'final']);
        $course = Course::query()->findOrFail($session->course_id);
        $course->update(['status' => CourseStatusEnum::PUBLISHED]);

        return $course;
    }

    public function testProgramMarksBuilderTopicsAndNotTopicsAddedByHand(): void
    {
        $course = $this->appliedCourse();
        $student = $this->student();
        $course->users()->attach($student);
        $topicIds = Topic::query()->whereIn('lesson_id', $course->lessons()->pluck('id'))->pluck('id');
        $this->assertGreaterThan(2, $topicIds->count());
        $byHand = (int) $topicIds->first();
        $retired = (int) $topicIds->last();
        // a topic the author later created by hand has no entity-map entry; a retired entry no longer counts
        EntityMapEntry::query()->whereIn('entity_type', ['topic', 'quiz_topic'])->where('entity_id', $byHand)->delete();
        EntityMapEntry::query()->whereIn('entity_type', ['topic', 'quiz_topic'])->where('entity_id', $retired)->update(['retired_at' => now()]);

        $json = $this->actingAs($student, 'api')->getJson('api/courses/' . $course->getKey() . '/program')->assertOk()->json('data');
        $topics = collect($this->topicsIn($json))->keyBy('id');

        $this->assertSame($topicIds->count(), $topics->count());
        $this->assertFalse($topics[$byHand]['ai_assisted']);
        $this->assertFalse($topics[$retired]['ai_assisted']);
        $others = $topics->except([$byHand, $retired]);
        $this->assertGreaterThan(0, $others->count());
        foreach ($others as $topic) {
            $this->assertTrue($topic['ai_assisted'], 'topic ' . $topic['id'] . ' came from the builder');
        }
    }

    public function testAStudentWithoutAccessToTheCourseStillGetsNothing(): void
    {
        $course = $this->appliedCourse();

        $this->actingAs($this->student(), 'api')->getJson('api/courses/' . $course->getKey() . '/program')->assertForbidden();
    }

    public function testALessonIsLookedUpOnceAndAUnknownTopicIsNotAssisted(): void
    {
        $course = $this->appliedCourse();
        $topics = Topic::query()->with('lesson')->whereIn('lesson_id', $course->lessons()->pluck('id'))->get();
        $lookup = new AiAssistedTopics();
        $lesson = $topics->first()->lesson;
        $siblings = $topics->where('lesson_id', $lesson->getKey())->each(fn (Topic $t) => $t->setRelation('lesson', $lesson));

        DB::enableQueryLog();
        foreach ($siblings as $topic) {
            $this->assertTrue($lookup->is($topic));
        }
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, $queries, 'one topic query and one entity-map query per lesson');
        $unknown = new Topic();
        $unknown->id = 999999999;
        $this->assertFalse($lookup->is($unknown), 'a topic with no lesson and no entry');
    }

    public function testTheLabelSettingIsOnByDefaultAndPublic(): void
    {
        $this->assertTrue(config('ai.label_generated_courses'));
        $config = $this->getJson('api/config')->assertOk()->json('data');
        $this->assertTrue($config['ai']['label_generated_courses']);
    }
}
