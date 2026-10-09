<?php

namespace Ulams\Interactive\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Models\InteractiveProgress;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;
use Ulams\Interactive\Tests\TestCase;

class InteractiveLearnerTest extends TestCase
{
    use CreatesUsers;

    private Course $course;
    private InteractivePackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        Storage::fake(config('filesystems.default'));
        config(['ulams_uploads.content_origin' => 'http://coffee.content.localhost']);
        $this->course = Course::factory()->create(['status' => 'published']);
        $this->package = app(InteractivePackageServiceContract::class)->create($this->upload($this->packageZip('steps')), null, null);
    }

    private function topic(array $content = []): Topic
    {
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $topic->topicable()->associate(InteractiveTopic::query()->create($content + ['value' => $this->package->getKey()]))->save();

        return $topic->refresh();
    }

    private function student()
    {
        $student = $this->makeStudent();
        $this->course->users()->attach($student->getKey());

        return $student;
    }

    private function events($student, Topic $topic, array $events)
    {
        return $this->actingAs($student, 'api')->postJson("/api/interactive/topics/{$topic->getKey()}/events", ['events' => $events]);
    }

    private function progressStatus(Topic $topic, $student): ?int
    {
        return $topic->progress()->where('user_id', $student->getKey())->value('status');
    }

    // ---- launch

    public function testAnEnrolledLearnerLaunchesThePinnedVersionAndGetsNoToken(): void
    {
        $topic = $this->topic(['start_step' => 'middle', 'end_step' => 'last', 'display' => 'background', 'text' => 'Read this']);
        $student = $this->student();

        $data = $this->actingAs($student, 'api')->postJson("/api/interactive/launches/{$topic->getKey()}")->assertOk()->json('data');

        $key = $this->package->storage_key;
        $this->assertSame("http://coffee.content.localhost/interactive/{$key}/v1/index.html", $data['url']);
        $this->assertSame(1, $data['version']);
        $this->assertSame(['intro', 'middle', 'last'], array_column($data['manifest']['steps'], 'id'));
        $this->assertSame('Zacznij tutaj.', $data['manifest']['steps'][0]['text']['pl']);
        $this->assertSame("http://coffee.content.localhost/interactive/{$key}/v1/posters/intro.webp", $data['manifest']['steps'][0]['poster']);
        $this->assertArrayNotHasKey('poster', $data['manifest']['steps'][1]);
        $this->assertSame('CC-BY-4.0', $data['manifest']['licence']);
        $this->assertSame(['webgl'], $data['manifest']['requires']);
        $this->assertSame(['start_step' => 'middle', 'end_step' => 'last', 'completion_rule' => 'on_range_end', 'pass_score' => null, 'display' => 'background', 'height' => 640, 'text' => 'Read this'], $data['topic']);
        // nothing that looks like a credential reaches the frame
        $this->assertStringNotContainsString('token', strtolower(json_encode($data)));
        $this->assertStringNotContainsString('?', $data['url']);
        $this->assertStringNotContainsString('#', $data['url']);
        $this->assertSame(ProgressStatus::IN_PROGRESS, $this->progressStatus($topic, $student));
    }

    public function testLaunchNeedsAccessAnInteractiveTopicAndAContentOrigin(): void
    {
        $topic = $this->topic();
        $this->postJson("/api/interactive/launches/{$topic->getKey()}")->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson("/api/interactive/launches/{$topic->getKey()}")->assertForbidden();

        $student = $this->student();
        $plain = Topic::factory()->create(['lesson_id' => $topic->lesson_id]);
        $this->actingAs($student, 'api')->postJson("/api/interactive/launches/{$plain->getKey()}")->assertNotFound();
        $this->actingAs($student, 'api')->postJson('/api/interactive/launches/99999999')->assertNotFound();

        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);
        $this->actingAs($student, 'api')->postJson("/api/interactive/launches/{$topic->getKey()}")->assertStatus(503);
    }

    public function testLaunchAnswers404WhenTheTypeIsSwitchedOffForTheTenant(): void
    {
        $topic = $this->topic();
        config(['ulams_interactive.enabled' => false]);

        $this->actingAs($this->student(), 'api')->postJson("/api/interactive/launches/{$topic->getKey()}")->assertNotFound();
        $this->events($this->student(), $topic, [['type' => 'complete']])->assertNotFound();
    }

    public function testOnOpenCompletesOnLaunch(): void
    {
        $topic = $this->topic(['completion_rule' => 'on_open']);
        $student = $this->student();

        $this->actingAs($student, 'api')->postJson("/api/interactive/launches/{$topic->getKey()}")->assertOk();

        $this->assertSame(ProgressStatus::COMPLETE, $this->progressStatus($topic, $student));
        // opening again keeps it complete
        $this->actingAs($student, 'api')->postJson("/api/interactive/launches/{$topic->getKey()}")->assertOk();
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressStatus($topic, $student));
    }

    // ---- completion rules

    public function testRangeEndCompletesOnTheEndStepOrOnComplete(): void
    {
        $topic = $this->topic(['start_step' => 'middle', 'end_step' => 'last']);
        $student = $this->student();

        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'middle']])->assertOk()
            ->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS)->assertJsonPath('data.progress.last_step', 'middle');
        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'last']])->assertOk()
            ->assertJsonPath('data.status', ProgressStatus::COMPLETE)->assertJsonPath('data.progress.completed', true);
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressStatus($topic, $student));

        $other = $this->topic(['start_step' => 'intro', 'end_step' => 'middle']);
        $this->events($student, $other, [['type' => 'complete']])->assertOk()->assertJsonPath('data.status', ProgressStatus::COMPLETE);
    }

    public function testTheLastStepEndsARangeWithoutAnEndStep(): void
    {
        $topic = $this->topic();
        $student = $this->student();

        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'middle']])->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS);
        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'last']])->assertJsonPath('data.status', ProgressStatus::COMPLETE);
    }

    public function testStepsOutsideTheRangeAreRecordedButNeverComplete(): void
    {
        $topic = $this->topic(['start_step' => 'intro', 'end_step' => 'middle']);
        $student = $this->student();

        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'last']])->assertOk()
            ->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS)->assertJsonPath('data.progress.last_step', 'last');
        // a step the manifest does not know is ignored
        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'ghost']])->assertOk()->assertJsonPath('data.progress.last_step', 'last');
        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'middle']])->assertJsonPath('data.status', ProgressStatus::COMPLETE);
    }

    public function testOnCompleteWaitsForTheCompleteMessage(): void
    {
        $topic = $this->topic(['completion_rule' => 'on_complete', 'end_step' => 'last']);
        $student = $this->student();

        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'last'], ['type' => 'progress', 'value' => 1]])->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS);
        $this->events($student, $topic, [['type' => 'complete']])->assertJsonPath('data.status', ProgressStatus::COMPLETE);
    }

    public function testOnScoreNeedsThePassPercentageAndKeepsTheBestScore(): void
    {
        $topic = $this->topic(['completion_rule' => 'on_score', 'pass_score' => 80]);
        $student = $this->student();

        $this->events($student, $topic, [['type' => 'complete'], ['type' => 'score', 'raw' => 7, 'max' => 10]])
            ->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS)->assertJsonPath('data.progress.score_raw', 7);
        $this->events($student, $topic, [['type' => 'score', 'raw' => 5, 'max' => 10]])->assertJsonPath('data.progress.score_raw', 7);
        $this->events($student, $topic, [['type' => 'score', 'raw' => 4, 'max' => 5]])
            ->assertJsonPath('data.status', ProgressStatus::COMPLETE)->assertJsonPath('data.progress.score_raw', 4)->assertJsonPath('data.progress.score_max', 5);
        // a later worse score does not undo the best one
        $this->events($student, $topic, [['type' => 'score', 'raw' => 1, 'max' => 10]])->assertJsonPath('data.progress.score_raw', 4);
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressStatus($topic, $student));
    }

    public function testProgressKeepsTheMaximumAndACompletedTopicStaysCompleted(): void
    {
        $topic = $this->topic();
        $student = $this->student();

        $this->events($student, $topic, [['type' => 'progress', 'value' => 0.6], ['type' => 'progress', 'value' => 0.2]])->assertJsonPath('data.progress.max_progress', 0.6);
        $this->events($student, $topic, [['type' => 'complete']])->assertJsonPath('data.status', ProgressStatus::COMPLETE);
        $this->events($student, $topic, [['type' => 'stepChanged', 'step' => 'intro']])->assertJsonPath('data.status', ProgressStatus::COMPLETE);
        $this->assertSame(1, InteractiveProgress::query()->where('topic_id', $topic->getKey())->count());
        $this->assertSame(1, $topic->progress()->where('user_id', $student->getKey())->count());
    }

    public function testEachLearnerHasTheirOwnProgress(): void
    {
        $topic = $this->topic();
        $first = $this->student();
        $second = $this->student();

        $this->events($first, $topic, [['type' => 'complete']])->assertJsonPath('data.status', ProgressStatus::COMPLETE);
        $this->events($second, $topic, [['type' => 'stepChanged', 'step' => 'intro']])->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS);
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressStatus($topic, $first));
        $this->assertSame(ProgressStatus::IN_PROGRESS, $this->progressStatus($topic, $second));
    }

    // ---- events endpoint

    public function testEventsNeedAccessAndAnInteractiveTopic(): void
    {
        $topic = $this->topic();
        $this->postJson("/api/interactive/topics/{$topic->getKey()}/events", ['events' => [['type' => 'complete']]])->assertUnauthorized();
        $this->events($this->makeStudent(), $topic, [['type' => 'complete']])->assertForbidden();

        $plain = Topic::factory()->create(['lesson_id' => $topic->lesson_id]);
        $this->events($this->student(), $plain, [['type' => 'complete']])->assertNotFound();
    }

    public function testBatchesAreLimitedAndEveryEventIsCheckedAgainstItsSchema(): void
    {
        $topic = $this->topic();
        $student = $this->student();

        $this->events($student, $topic, [])->assertUnprocessable();
        $this->actingAs($student, 'api')->postJson("/api/interactive/topics/{$topic->getKey()}/events", [])->assertUnprocessable();
        $this->events($student, $topic, array_fill(0, 41, ['type' => 'progress', 'value' => 0.1]))->assertUnprocessable();
        $this->events($student, $topic, array_fill(0, 40, ['type' => 'progress', 'value' => 0.1]))->assertOk();

        foreach ([
            ['type' => 'init'],
            ['type' => 'goToStep', 'step' => 'intro'],
            ['type' => 'nope'],
            ['step' => 'intro'],
            ['type' => 'stepChanged', 'step' => 'Not An Id'],
            ['type' => 'stepChanged'],
            ['type' => 'progress', 'value' => 2],
            ['type' => 'progress', 'value' => 'half'],
            ['type' => 'score', 'raw' => 1, 'max' => 0],
            ['type' => 'score', 'raw' => -1, 'max' => 10],
            ['type' => 'event', 'verb' => 'interacted', 'object' => 'x'],
            ['type' => 'event', 'verb' => 'http://adlnet.gov/expapi/verbs/interacted', 'object' => 'Bad Object'],
            ['type' => 'complete', 'extra' => 'field'],
        ] as $bad) {
            $this->events($student, $topic, [$bad])->assertUnprocessable();
        }
        $this->events($student, $topic, [['type' => 'complete'], ['type' => 'nope']])->assertUnprocessable();
        $this->assertSame(ProgressStatus::IN_PROGRESS, $this->progressStatus($topic, $student), 'a rejected batch changes nothing');
    }

    public function testTheEnvelopeFieldsOfABridgeMessageAreAccepted(): void
    {
        $topic = $this->topic();
        $this->events($this->student(), $topic, [['ulams-ix' => 1, 'nonce' => 'abcdefgh12345678', 'type' => 'stepChanged', 'step' => 'intro']])
            ->assertOk()->assertJsonPath('data.progress.last_step', 'intro');
    }

    public function testEventsAreRateLimited(): void
    {
        $topic = $this->topic();
        $student = $this->student();
        for ($i = 0; $i < 60; $i++) {
            $this->events($student, $topic, [['type' => 'progress', 'value' => 0.1]])->assertOk();
        }
        $this->events($student, $topic, [['type' => 'progress', 'value' => 0.1]])->assertStatus(429);
    }

    public function testXapiLikeEventsAreDroppedWithoutAnLrsAccess(): void
    {
        $topic = $this->topic();

        $this->events($this->student(), $topic, [['type' => 'event', 'verb' => 'http://adlnet.gov/expapi/verbs/interacted', 'object' => 'slider', 'result' => ['response' => '11.2']]])
            ->assertOk()->assertJsonPath('data.status', ProgressStatus::IN_PROGRESS);
    }
}
