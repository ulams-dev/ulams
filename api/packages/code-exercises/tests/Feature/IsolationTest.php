<?php

namespace Ulams\CodeExercises\Tests\Feature;

use Ulams\CodeExercises\Models\CodeExerciseAttempt;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\CodeExercises\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;

/**
 * Isolation for every endpoint of the package. Tenants have their own database, so an id from another tenant
 * is an unknown id here (404; the cross-tenant HTTP check is packages/tenancy/tests/Integration). Inside a
 * tenant: a learner outside the course gets 403, a guest 401, another learner never sees my attempts, and
 * the report and the preview are for people who may edit the course.
 */
class IsolationTest extends TestCase
{
    use CreatesUsers;

    private Course $course;
    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_uploads.content_origin' => 'http://coffee.content.localhost']);
        $this->course = Course::factory()->create(['status' => 'published']);
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $this->topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $this->topic->topicable()->associate(CodeExerciseTopic::query()->create($this->exercise()))->save();
    }

    /** @return array<int, array{0: string, 1: string, 2: array<string, mixed>}> */
    private function learnerEndpoints(int $id): array
    {
        return [
            ['POST', "/api/code-exercises/launches/{$id}", []],
            ['POST', "/api/code-exercises/topics/{$id}/attempts", ['results' => [['name' => 'a', 'status' => 'pass']]]],
            ['GET', "/api/code-exercises/topics/{$id}/attempts", []],
        ];
    }

    /** @return array<int, array{0: string, 1: string, 2: array<string, mixed>}> */
    private function adminEndpoints(int $id): array
    {
        return [
            ['GET', "/api/admin/code-exercises/topics/{$id}/preview", []],
            ['GET', "/api/admin/code-exercises/topics/{$id}/attempts", []],
        ];
    }

    public function testGuestsAreRefusedOnEveryEndpoint(): void
    {
        foreach (array_merge($this->learnerEndpoints($this->topic->getKey()), $this->adminEndpoints($this->topic->getKey())) as [$method, $uri, $body]) {
            $this->json($method, $uri, $body)->assertUnauthorized();
        }
    }

    public function testALearnerOutsideTheCourseGetsNothing(): void
    {
        $this->course->update(['status' => 'draft']);
        $stranger = $this->makeStudent();

        foreach ($this->learnerEndpoints($this->topic->getKey()) as [$method, $uri, $body]) {
            $this->actingAs($stranger, 'api')->json($method, $uri, $body)->assertForbidden();
        }
        $this->assertSame(0, CodeExerciseAttempt::query()->count());
    }

    public function testLearnersAreRefusedOnTheAdminEndpoints(): void
    {
        $student = $this->makeStudent();
        $this->course->users()->attach($student->getKey());

        foreach ($this->adminEndpoints($this->topic->getKey()) as [$method, $uri]) {
            $this->actingAs($student, 'api')->json($method, $uri)->assertForbidden();
        }
    }

    public function testAnotherTutorWhoDoesNotOwnTheCourseIsRefusedOnTheAdminEndpoints(): void
    {
        foreach ($this->adminEndpoints($this->topic->getKey()) as [$method, $uri]) {
            $this->actingAs($this->makeInstructor(), 'api')->json($method, $uri)->assertForbidden();
        }
    }

    public function testUnknownTopicsAndOtherTopicTypesAreNotFound(): void
    {
        $admin = $this->makeAdmin();
        $plain = Topic::factory()->create(['lesson_id' => $this->topic->lesson_id]);
        foreach ([99999999, $plain->getKey()] as $id) {
            foreach (array_merge($this->learnerEndpoints($id), $this->adminEndpoints($id)) as [$method, $uri, $body]) {
                $this->actingAs($admin, 'api')->json($method, $uri, $body)->assertNotFound();
            }
        }
    }

    public function testAnotherLearnerNeverSeesMyAttemptsInTheHistoryOrTheLaunch(): void
    {
        $mine = $this->makeStudent();
        $theirs = $this->makeStudent();
        foreach ([$mine, $theirs] as $s) {
            $this->course->users()->attach($s->getKey());
        }
        $this->actingAs($mine, 'api')->postJson("/api/code-exercises/topics/{$this->topic->getKey()}/attempts", ['results' => [['name' => 'a', 'status' => 'pass']]])->assertCreated();

        $this->assertSame([], $this->actingAs($theirs, 'api')->getJson("/api/code-exercises/topics/{$this->topic->getKey()}/attempts")->assertOk()->json('data'));
        $this->assertSame(0, $this->actingAs($theirs, 'api')->postJson("/api/code-exercises/launches/{$this->topic->getKey()}")->json('data.attempts.count'));
    }

    public function testTheReportListsEveryLearnersAttemptsForTheCourseAuthorOnly(): void
    {
        $a = $this->makeStudent(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $b = $this->makeStudent();
        foreach ([$a, $b] as $s) {
            $this->course->users()->attach($s->getKey());
            $this->actingAs($s, 'api')->postJson("/api/code-exercises/topics/{$this->topic->getKey()}/attempts", ['results' => [['name' => 'a', 'status' => 'fail']]])->assertCreated();
        }

        $rows = $this->actingAs($this->makeAdmin(), 'api')->getJson("/api/admin/code-exercises/topics/{$this->topic->getKey()}/attempts")
            ->assertOk()->assertJsonPath('meta.total', 2)->json('data');

        $this->assertEqualsCanonicalizing([$a->getKey(), $b->getKey()], array_column(array_column($rows, 'user'), 'id'));
        $this->assertContains('Ada Lovelace', array_column(array_column($rows, 'user'), 'name'));
        $this->assertSame([false, false], array_column($rows, 'verified'));
    }

    public function testThePreviewReturnsTheExerciseWithoutTrackingAnything(): void
    {
        $admin = $this->makeAdmin();

        $data = $this->actingAs($admin, 'api')->getJson("/api/admin/code-exercises/topics/{$this->topic->getKey()}/preview")->assertOk()->json('data');

        $this->assertSame('http://coffee.content.localhost/runtime/code-runner/1/runner.html', $data['runner']['url']);
        $this->assertSame($this->starterFiles(), $data['exercise']['files']);
        $this->assertArrayNotHasKey('attempts', $data);
        $this->assertSame(0, $this->topic->progress()->count());
    }
}
