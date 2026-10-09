<?php

namespace Ulams\LiaScript\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\LiaScript\Models\LiaScriptDocument;
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
        // same-site hardening: an http(s) API base and a path on this origin only
        $this->assertStringContainsString('isHttpUrl(api)', $disk->get('liascript/_player/player.js'));
        $this->assertStringContainsString("course.charAt(1) === '/'", $disk->get('liascript/_player/player.js'));
        $disk->assertExists('liascript/_player/build/assets/app.js');
        $this->assertStringContainsString('<script src="config.js">', $disk->get('liascript/_player/build/index.html'));
        // the build takes the SCORM API from our page before it would read window.top (another origin)
        $disk->assertExists('liascript/_player/api-bridge.js');
        $this->assertStringContainsString('<script src="config.js"></script><script src="../api-bridge.js"></script>', $disk->get('liascript/_player/build/index.html'));
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

    public function testTheEditorPreviewsUnsavedTextWithTheCurrentAssets(): void
    {
        $admin = $this->makeAdmin();
        $draft = self::COURSE . "\n## Draft section\n\nNot saved yet.\n";

        $data = $this->actingAs($admin, 'api')
            ->postJson("/api/admin/liascript/{$this->documentId}/preview", ['markdown' => $draft])
            ->assertOk()
            ->json('data');

        $this->assertSame(4, $data['sections']);
        $this->assertStringStartsWith('http://coffee.content.localhost/liascript/_player/index.html#', $data['url']);
        parse_str(parse_url($data['url'], PHP_URL_FRAGMENT), $fragment);
        $this->assertSame('1', $fragment['preview']);
        $this->assertArrayNotHasKey('token', $fragment);
        $this->assertMatchesRegularExpression("#^/liascript/{$this->documentId}/v2/preview-[0-9a-f]{32}\\.md\$#", $fragment['course']);

        $disk = Storage::disk(config('filesystems.default'));
        $this->assertSame(trim($draft), trim($disk->get(ltrim($fragment['course'], '/'))));
        // relative asset links of the draft resolve: the current version's assets are next to it
        $this->assertTrue($disk->exists("liascript/{$this->documentId}/v2/img/x.png"));
        $this->assertTrue($disk->exists('liascript/_player/index.html'));
        // nothing was saved
        $this->assertSame(2, LiaScriptDocument::query()->find($this->documentId)->current_version);
    }

    public function testOldPreviewsArePruned(): void
    {
        $admin = $this->makeAdmin();
        config(['ulams_liascript.preview_keep' => 2]);
        $disk = Storage::disk(config('filesystems.default'));
        $folder = "liascript/{$this->documentId}/v2";
        $previews = fn () => array_values(array_filter($disk->files($folder), fn ($f) => str_contains($f, '/preview-')));

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($admin, 'api')->postJson("/api/admin/liascript/{$this->documentId}/preview", ['markdown' => "# Draft {$i}"])->assertOk();
        }
        $this->assertCount(3, $previews());

        config(['ulams_liascript.preview_ttl' => -1]);
        $this->actingAs($admin, 'api')->postJson("/api/admin/liascript/{$this->documentId}/preview", ['markdown' => '# Last'])->assertOk();
        $this->assertCount(1, $previews());
        $this->assertSame('# Last', $disk->get($previews()[0]));
    }

    public function testThePreviewValidatesTheTextAndNeedsPermissionAndAContentOrigin(): void
    {
        $admin = $this->makeAdmin();
        $url = "/api/admin/liascript/{$this->documentId}/preview";

        $this->postJson($url, ['markdown' => '# x'])->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson($url, ['markdown' => '# x'])->assertForbidden();
        $this->actingAs($admin, 'api')->postJson($url, ['markdown' => '   '])->assertUnprocessable();
        $this->actingAs($admin, 'api')->postJson($url, ['markdown' => "ba\0d"])->assertUnprocessable();
        $this->actingAs($admin, 'api')->postJson('/api/admin/liascript/999999999/preview', ['markdown' => '# x'])->assertNotFound();

        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);
        $this->actingAs($admin, 'api')->postJson($url, ['markdown' => '# x'])->assertStatus(503);
    }

    public function testThePreviewStaysOnTheRequestingTenantsOriginAndDisk(): void
    {
        $admin = $this->makeAdmin();
        $coffeeDisk = Storage::disk(config('filesystems.default'));

        // another tenant: its own content origin and its own bucket
        config(['ulams_uploads.content_origin' => 'http://oncall.content.localhost', 'ulams_liascript.disk' => 'oncall']);
        config(['filesystems.disks.oncall' => ['driver' => 'local', 'root' => sys_get_temp_dir() . '/oncall-' . bin2hex(random_bytes(4))]]);
        $oncallDisk = Storage::fake('oncall');
        app(LiaScriptService::class)->update(LiaScriptDocument::query()->find($this->documentId), null, "# Oncall\n", null, null, null);

        $url = $this->actingAs($admin, 'api')
            ->postJson("/api/admin/liascript/{$this->documentId}/preview", ['markdown' => '# Draft'])
            ->assertOk()
            ->json('data.url');

        $this->assertStringStartsWith('http://oncall.content.localhost/', $url);
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->assertTrue($oncallDisk->exists(ltrim($fragment['course'], '/')));
        $this->assertFalse($coffeeDisk->exists(ltrim($fragment['course'], '/')));
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
