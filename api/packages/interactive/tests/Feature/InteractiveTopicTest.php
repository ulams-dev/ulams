<?php

namespace Ulams\Interactive\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;
use Ulams\Interactive\Tests\TestCase;

class InteractiveTopicTest extends TestCase
{
    use CreatesUsers;

    private int $lessonId;
    private int $packageId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        $this->lessonId = Lesson::factory()->create(['course_id' => Course::factory()->create()->getKey()])->getKey();
        $this->packageId = app(InteractivePackageServiceContract::class)->create($this->upload($this->packageZip('steps')), null, null)->getKey();
    }

    private function createTopic(array $content, ?int $lesson = null)
    {
        return $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/topics', [
            'title' => 'Interactive lesson',
            'lesson_id' => $lesson ?? $this->lessonId,
            'topicable_type' => InteractiveTopic::class,
        ] + $content);
    }

    public function testATopicPinsTheCurrentVersionByDefault(): void
    {
        $this->createTopic(['value' => $this->packageId, 'start_step' => 'middle', 'end_step' => 'last', 'display' => 'background', 'height' => 500, 'text' => '# Hello'])
            ->assertCreated()
            ->assertJsonPath('data.topicable.version', 1)
            ->assertJsonPath('data.topicable.resolved_version', 1)
            ->assertJsonPath('data.topicable.follow_latest', false)
            ->assertJsonPath('data.topicable.start_step', 'middle')
            ->assertJsonPath('data.topicable.completion_rule', 'on_range_end')
            ->assertJsonPath('data.topicable.display', 'background')
            ->assertJsonPath('data.topicable.title', 'A stepped interactive');
    }

    public function testAnUploadNeverChangesWhatAPinnedTopicPlaysButAFollowingTopicMoves(): void
    {
        $pinned = $this->createTopic(['value' => $this->packageId])->assertCreated()->json('data.topicable.id');
        $following = $this->createTopic(['value' => $this->packageId, 'follow_latest' => true])->assertCreated()->json('data.topicable.id');

        app(InteractivePackageServiceContract::class)->addVersion(
            \Ulams\Interactive\Models\InteractivePackage::query()->findOrFail($this->packageId),
            $this->upload($this->packageZip('steps', ['version' => '2.2.0'])),
            null
        );

        $this->assertSame(1, InteractiveTopic::query()->findOrFail($pinned)->resolveVersion()->version);
        $this->assertSame(2, InteractiveTopic::query()->findOrFail($following)->resolveVersion()->version);
        $this->assertNull(InteractiveTopic::query()->findOrFail($following)->version);
    }

    public function testRepinningAndFollowingLatestThroughTheTopicApi(): void
    {
        $topic = $this->createTopic(['value' => $this->packageId])->assertCreated()->json('data');
        app(InteractivePackageServiceContract::class)->addVersion(
            \Ulams\Interactive\Models\InteractivePackage::query()->findOrFail($this->packageId),
            $this->upload($this->packageZip('steps')),
            null
        );

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')->patchJson("/api/admin/topics/{$topic['id']}", ['topicable_type' => InteractiveTopic::class, 'follow_latest' => true, 'value' => $this->packageId])
            ->assertOk()->assertJsonPath('data.topicable.resolved_version', 2)->assertJsonPath('data.topicable.version', null);
        $this->actingAs($admin, 'api')->patchJson("/api/admin/topics/{$topic['id']}", ['topicable_type' => InteractiveTopic::class, 'follow_latest' => false, 'version' => 1, 'value' => $this->packageId])
            ->assertOk()->assertJsonPath('data.topicable.resolved_version', 1);
    }

    public function testStepsMustExistInThePlayedVersion(): void
    {
        $this->createTopic(['value' => $this->packageId, 'start_step' => 'nope'])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'end_step' => 'nope'])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'start_step' => 'last', 'end_step' => 'intro'])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'version' => 9])->assertUnprocessable();
        $this->assertSame(0, InteractiveTopic::query()->count());
    }

    public function testStepsOfAPinnedVersionAreCheckedAgainstThatVersion(): void
    {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/packages/steps/ulams-interactive.json'), true);
        $manifest['steps'][] = ['id' => 'bonus', 'title' => ['en' => 'B', 'pl' => 'B'], 'text' => ['en' => 'b', 'pl' => 'b']];
        app(InteractivePackageServiceContract::class)->addVersion(
            \Ulams\Interactive\Models\InteractivePackage::query()->findOrFail($this->packageId),
            $this->upload($this->packageZip('steps', ['steps' => $manifest['steps']])),
            null
        );

        $this->createTopic(['value' => $this->packageId, 'version' => 1, 'end_step' => 'bonus'])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'version' => 2, 'end_step' => 'bonus'])->assertCreated();
        $this->createTopic(['value' => $this->packageId, 'follow_latest' => true, 'end_step' => 'bonus'])->assertCreated();
    }

    public function testFieldRules(): void
    {
        $this->createTopic(['value' => 999999])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'completion_rule' => 'always'])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'completion_rule' => 'on_score'])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'completion_rule' => 'on_score', 'pass_score' => 101])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'completion_rule' => 'on_score', 'pass_score' => 80])->assertCreated()->assertJsonPath('data.topicable.pass_score', 80);
        $this->createTopic(['value' => $this->packageId, 'display' => 'fullscreen'])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'height' => 100])->assertUnprocessable();
        $this->createTopic(['value' => $this->packageId, 'height' => 5000])->assertUnprocessable();
    }

    public function testThePackageCannotBeDeletedWhileATopicUsesItAtTheDatabaseLevelToo(): void
    {
        $this->createTopic(['value' => $this->packageId])->assertCreated();
        $this->expectException(\Illuminate\Database\QueryException::class);
        \Ulams\Interactive\Models\InteractivePackage::query()->whereKey($this->packageId)->delete();
    }
}
