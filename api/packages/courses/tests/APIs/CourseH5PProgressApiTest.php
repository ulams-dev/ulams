<?php

namespace Ulams\Courses\Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\H5PUserProgress;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\Models\User;
use Ulams\Courses\Tests\TestCase;

/**
 * `POST /api/courses/progress/{topic}/h5p`: the SDK (Astro front) sends the xAPI statement object
 * as `event`; older clients send the verb IRI as `event` and the statement as `data`.
 */
class CourseH5PProgressApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $this->topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $this->user->courses()->sync([$course->getKey()]);
    }

    private function statement(string $verb = 'http://adlnet.gov/expapi/verbs/answered'): array
    {
        return [
            'actor' => ['objectType' => 'Agent', 'name' => 'Learner'],
            'verb' => ['id' => $verb, 'display' => ['en-US' => 'answered']],
            'object' => ['id' => 'http://example.test/h5p/1', 'objectType' => 'Activity'],
            'result' => ['score' => ['raw' => 3, 'max' => 5]],
        ];
    }

    private function sendEvent(array $body)
    {
        return $this->actingAs($this->user, 'api')->postJson('/api/courses/progress/' . $this->topic->getKey() . '/h5p', $body);
    }

    public function test_a_user_without_access_to_the_course_cannot_store_events(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'api')
            ->postJson('/api/courses/progress/' . $this->topic->getKey() . '/h5p', ['event' => $this->statement()])
            ->assertForbidden();

        $this->assertSame(0, H5PUserProgress::query()->where('user_id', $stranger->getKey())->count());
    }

    public function test_guests_and_unknown_topics_are_refused(): void
    {
        $this->postJson('/api/courses/progress/' . $this->topic->getKey() . '/h5p', ['event' => $this->statement()])->assertUnauthorized();
        // a topic of another tenant is not in this tenant's database
        $this->actingAs($this->user, 'api')->postJson('/api/courses/progress/999999/h5p', ['event' => $this->statement()])->assertNotFound();
    }

    public function test_a_statement_object_as_the_event_is_stored_as_json(): void
    {
        $statement = $this->statement();

        $this->sendEvent(['event' => $statement])->assertOk()->assertJsonPath('data.status', true);

        $row = H5PUserProgress::query()->where('topic_id', $this->topic->getKey())->where('user_id', $this->user->getKey())->firstOrFail();
        $this->assertSame('http://adlnet.gov/expapi/verbs/answered', $row->event);
        $this->assertEquals($statement, $row->data);
    }

    public function test_a_verb_and_data_still_work(): void
    {
        $this->sendEvent(['event' => 'http://adlnet.gov/expapi/verbs/attempted', 'data' => ['score' => 1]])->assertOk();

        $row = H5PUserProgress::query()->where('topic_id', $this->topic->getKey())->firstOrFail();
        $this->assertSame('http://adlnet.gov/expapi/verbs/attempted', $row->event);
        $this->assertSame(['score' => 1], $row->data);
    }

    public function test_a_verb_without_data_stores_an_empty_document(): void
    {
        $this->sendEvent(['event' => 'http://adlnet.gov/expapi/verbs/attempted'])->assertOk();

        $this->assertSame([], H5PUserProgress::query()->where('topic_id', $this->topic->getKey())->firstOrFail()->data);
    }

    public function test_a_later_statement_with_the_same_verb_replaces_the_stored_one(): void
    {
        $this->sendEvent(['event' => $this->statement()])->assertOk();
        $newer = $this->statement();
        $newer['result']['score']['raw'] = 5;
        $this->sendEvent(['event' => $newer])->assertOk();

        $rows = H5PUserProgress::query()->where('topic_id', $this->topic->getKey())->get();
        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]->data['result']['score']['raw']);
    }

    public function test_a_statement_without_a_verb_is_stored_under_a_placeholder_name(): void
    {
        $this->sendEvent(['event' => ['object' => ['id' => 'http://example.test/h5p/1']]])->assertOk();

        $this->assertSame('statement', H5PUserProgress::query()->where('topic_id', $this->topic->getKey())->firstOrFail()->event);
    }

    public function test_a_missing_or_malformed_event_is_rejected(): void
    {
        $this->sendEvent([])->assertUnprocessable()->assertJsonValidationErrors(['event']);
        $this->sendEvent(['event' => 5])->assertUnprocessable()->assertJsonValidationErrors(['event']);
        $this->sendEvent(['event' => 'x', 'data' => 'text'])->assertUnprocessable()->assertJsonValidationErrors(['data']);
        $this->assertSame(0, H5PUserProgress::query()->count());
    }

    public function test_guests_are_refused(): void
    {
        $this->postJson('/api/courses/progress/' . $this->topic->getKey() . '/h5p', ['event' => $this->statement()])->assertUnauthorized();
    }
}
