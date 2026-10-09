<?php

namespace Ulams\LiaScript\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\LiaScript\Services\LiaScriptPlayer;
use Ulams\LiaScript\Services\LiaScriptService;
use Ulams\LiaScript\Services\ProgressToken;
use Ulams\LiaScript\Tests\TestCase;

class LiaScriptPlayerTest extends TestCase
{
    use CreatesUsers;

    private const COURSE = "<!--\nauthor: ulams\n-->\n\n# Git\n\n## Commits\n\n```\n# not a heading\n```\n\n## Branches\n\n![x](img/x.png)\n";

    private string $build;
    private Course $course;
    private Topic $topic;
    private int $documentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        Storage::fake(config('filesystems.default'));

        // a stand-in for the fetched LiaScript build (bin/fetch-player.sh needs the network)
        $this->build = sys_get_temp_dir() . '/lia-build-' . bin2hex(random_bytes(4));
        mkdir($this->build . '/assets', 0777, true);
        file_put_contents($this->build . '/index.html', '<html><head><title>Lia</title></head><body></body></html>');
        file_put_contents($this->build . '/assets/app.js', 'lia()');
        file_put_contents($this->build . '/.version', 'test');
        config([
            'ulams_liascript.player_build_path' => $this->build,
            'ulams_uploads.content_origin' => 'http://coffee.content.localhost',
        ]);

        $zip = $this->makeZip(['README.md' => self::COURSE, 'img/x.png' => 'PNG']);
        $document = app(LiaScriptService::class)->create(null, null, new \Illuminate\Http\UploadedFile($zip, 'c.zip', null, null, true), null);
        // a text-only version 2: its asset still lives in v1 and must be copied on publish
        app(LiaScriptService::class)->update($document, null, self::COURSE . "\nMore.\n", null, null, null);
        $this->documentId = $document->getKey();

        $this->course = Course::factory()->create(['status' => 'published']);
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $content = LiaScriptTopic::query()->create(['value' => $this->documentId]);
        $this->topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $this->topic->topicable()->associate($content)->save();
    }

    protected function tearDown(): void
    {
        Storage::build(['driver' => 'local', 'root' => sys_get_temp_dir()])->deleteDirectory(basename($this->build));
        parent::tearDown();
    }

    public function testSectionsAreHeadingsOutsideCodeAndTheHeaderComment(): void
    {
        $this->assertSame(3, LiaScriptPlayer::sections(self::COURSE));
        $this->assertSame(1, LiaScriptPlayer::sections('no headings'));
    }

    public function testAnEnrolledLearnerLaunchesTheCurrentVersionOnTheContentOrigin(): void
    {
        $student = $this->enrolledStudent();

        $data = $this->actingAs($student, 'api')->postJson("/api/liascript/launches/{$this->topic->getKey()}")->assertOk()->json('data');

        $this->assertSame(2, $data['version']);
        $this->assertSame(3, $data['sections']);
        $this->assertStringStartsWith('http://coffee.content.localhost/liascript/_player/index.html#', $data['url']);
        parse_str(parse_url($data['url'], PHP_URL_FRAGMENT), $fragment);
        $this->assertSame("/liascript/{$this->documentId}/v2/README.md", $fragment['course']);
        $this->assertSame($student->getKey(), ProgressToken::verify($fragment['token'], $this->topic->getKey()));

        $disk = Storage::disk(config('filesystems.default'));
        $disk->assertExists("liascript/{$this->documentId}/v2/README.md");
        $disk->assertExists("liascript/{$this->documentId}/v2/img/x.png");
        $disk->assertExists('liascript/_player/index.html');
        $disk->assertExists('liascript/_player/player.js');
        $disk->assertExists('liascript/_player/build/assets/app.js');
        $this->assertStringContainsString('<script src="config.js">', $disk->get('liascript/_player/build/index.html'));
        $this->assertDatabaseHas('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::IN_PROGRESS]);
    }

    public function testReachingTheLastSectionCompletesTheTopic(): void
    {
        $student = $this->enrolledStudent();
        $token = ProgressToken::issue($student->getKey(), $this->topic->getKey(), 600);

        $this->progress($token, ['location' => 1, 'status' => 'not attempted'])->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->assertDatabaseMissing('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::COMPLETE]);

        $this->progress($token, ['location' => 2])->assertOk()->assertJsonPath('data.status', 'complete');
        $this->assertDatabaseHas('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::COMPLETE]);
    }

    public function testAPassedStatusCompletesTheTopic(): void
    {
        $student = $this->enrolledStudent();

        $this->progress(ProgressToken::issue($student->getKey(), $this->topic->getKey(), 600), ['location' => 0, 'status' => 'passed'])
            ->assertJsonPath('data.status', 'complete');
    }

    public function testLaunchNeedsAccessAContentOriginAndThePlayer(): void
    {
        $this->postJson("/api/liascript/launches/{$this->topic->getKey()}")->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson("/api/liascript/launches/{$this->topic->getKey()}")->assertForbidden();

        $student = $this->enrolledStudent();
        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);
        $this->actingAs($student, 'api')->postJson("/api/liascript/launches/{$this->topic->getKey()}")->assertStatus(503);

        config(['ulams_uploads.content_origin' => 'http://coffee.content.localhost', 'ulams_liascript.player_build_path' => '/nonexistent']);
        $this->actingAs($student, 'api')->postJson("/api/liascript/launches/{$this->topic->getKey()}")->assertStatus(503);
    }

    public function testProgressTokensAreBoundToTheirTopicTenantAndExpiry(): void
    {
        $student = $this->enrolledStudent();
        $other = Topic::factory()->create(['lesson_id' => $this->topic->lesson_id]);

        $this->progress(ProgressToken::issue($student->getKey(), $other->getKey(), 600), ['location' => 2])->assertUnauthorized();
        $this->progress(ProgressToken::issue($student->getKey(), $this->topic->getKey(), -1), ['location' => 2])->assertUnauthorized();
        $this->progress('forged.token', ['location' => 2])->assertUnauthorized();

        // tenant isolation: a token signed under another tenant's APP_KEY
        $token = ProgressToken::issue($student->getKey(), $this->topic->getKey(), 600);
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->progress($token, ['location' => 2])->assertUnauthorized();
        $this->assertDatabaseMissing('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::COMPLETE]);
    }

    public function testATopicIsCreatedThroughTheTopicApiAndUsedDocumentsCannotBeDeleted(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->postJson('/api/admin/topics', [
            'title' => 'LiaScript lesson',
            'lesson_id' => $this->topic->lesson_id,
            'topicable_type' => LiaScriptTopic::class,
            'value' => $this->documentId,
        ])->assertCreated()->assertJsonPath('data.topicable.version', 2);

        $this->actingAs($admin, 'api')->deleteJson("/api/admin/liascript/{$this->documentId}")->assertStatus(409);
    }

    private function enrolledStudent()
    {
        $student = $this->makeStudent();
        $this->course->users()->attach($student->getKey());

        return $student;
    }

    private function progress(string $token, array $body)
    {
        return $this->withHeaders(['X-Ulams-Tracking-Token' => $token])->postJson("/api/liascript/progress/{$this->topic->getKey()}", $body);
    }
}
