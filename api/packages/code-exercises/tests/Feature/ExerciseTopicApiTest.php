<?php

namespace Ulams\CodeExercises\Tests\Feature;

use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\CodeExercises\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;

/** Authoring: topics are created and changed through the topic API, and everything an author sends is validated. */
class ExerciseTopicApiTest extends TestCase
{
    use CreatesUsers;

    private int $lessonId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lessonId = Lesson::factory()->for(Course::factory())->create(['active' => true])->getKey();
    }

    private function postTopic(array $content, $as = null)
    {
        return $this->actingAs($as ?? $this->makeAdmin(), 'api')->postJson('/api/admin/topics', [
            'title' => 'Sum it',
            'lesson_id' => $this->lessonId,
            'topicable_type' => CodeExerciseTopic::class,
        ] + $content);
    }

    public function testCreatesAnExerciseWithStarterFilesAndVisibleTests(): void
    {
        $this->postTopic($this->exercise(['timeout_ms' => 8000, 'completion_rule' => 'on_attempt', 'pass_score' => 80]))
            ->assertCreated()
            ->assertJsonPath('data.topicable.language', 'javascript')
            ->assertJsonPath('data.topicable.runtime', 'browser')
            ->assertJsonPath('data.topicable.grading', 'practice')
            ->assertJsonPath('data.topicable.files.0.path', 'sum.js')
            ->assertJsonPath('data.topicable.visible_tests.0.path', 'sum.test.js')
            ->assertJsonPath('data.topicable.timeout_ms', 8000)
            ->assertJsonPath('data.topicable.completion_rule', 'on_attempt')
            ->assertJsonPath('data.topicable.pass_score', 80);

        $stored = CodeExerciseTopic::query()->firstOrFail();
        $this->assertSame($this->starterFiles(), $stored->files);
        $this->assertSame('browser', $stored->runtime);
        $this->assertSame('practice', $stored->grading);
    }

    public function testDefaultsApplyAndRuntimeAndGradingCannotBeSet(): void
    {
        $this->postTopic($this->exercise(['runtime' => 'server', 'grading' => 'verified']))->assertCreated();

        $stored = CodeExerciseTopic::query()->firstOrFail();
        $this->assertSame(5000, $stored->timeout_ms);
        $this->assertSame('on_pass', $stored->completion_rule);
        $this->assertSame(100, $stored->pass_score);
        $this->assertSame('browser', $stored->runtime, 'server grading arrives with the server runner');
        $this->assertSame('practice', $stored->grading);
    }

    public function testTypeScriptExercisesAreAccepted(): void
    {
        $this->postTopic($this->exercise([
            'language' => 'typescript',
            'files' => [['path' => 'sum.ts', 'content' => 'export const sum = (a: number, b: number): number => 0;']],
            'visible_tests' => [['path' => 'sum.test.ts', 'content' => "import { sum } from './sum';\ntest('adds', () => expect(sum(1, 2)).toBe(3));"]],
        ]))->assertCreated()->assertJsonPath('data.topicable.language', 'typescript');
    }

    public function testListsMayBeJsonStringsForMultipartForms(): void
    {
        $this->postTopic($this->exercise(['files' => json_encode($this->starterFiles()), 'visible_tests' => json_encode($this->visibleTests())]))
            ->assertCreated()
            ->assertJsonPath('data.topicable.files.0.path', 'sum.js');
        $this->assertSame($this->starterFiles(), CodeExerciseTopic::query()->firstOrFail()->files);

        $this->postTopic($this->exercise(['files' => '{not json']))->assertUnprocessable();
        $this->assertSame(1, CodeExerciseTopic::query()->count());
    }

    public function testTheTopicIsReadBackAndKeepsItsOwnCopyWhenCloned(): void
    {
        $id = $this->postTopic($this->exercise())->assertCreated()->json('data.id');
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->getJson("/api/admin/topics/{$id}")
            ->assertOk()
            ->assertJsonPath('data.topicable_type', CodeExerciseTopic::class)
            ->assertJsonPath('data.topicable.visible_tests.0.content', $this->visibleTests()[0]['content']);

        $this->actingAs($admin, 'api')->postJson("/api/admin/topics/{$id}/clone")->assertSuccessful();
        $this->assertSame(2, CodeExerciseTopic::query()->count());
        $this->assertSame($this->starterFiles(), CodeExerciseTopic::query()->latest('id')->firstOrFail()->files);
    }

    public function testTheFilesAreRevalidatedOnUpdate(): void
    {
        $id = $this->postTopic($this->exercise())->assertCreated()->json('data.id');
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->postJson("/api/admin/topics/{$id}", ['title' => 'Sum it', 'topicable_type' => CodeExerciseTopic::class] + $this->exercise(['files' => [['path' => '../evil.js', 'content' => 'x']]]))
            ->assertUnprocessable();
        $this->actingAs($admin, 'api')->postJson("/api/admin/topics/{$id}", ['title' => 'Sum it', 'topicable_type' => CodeExerciseTopic::class] + $this->exercise(['text' => 'New text']))
            ->assertSuccessful();
        $this->assertSame('New text', CodeExerciseTopic::query()->firstOrFail()->text);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function invalidContent(): array
    {
        $file = fn (string $path, string $content = 'x') => [['path' => $path, 'content' => $content]];
        $tests = [['path' => 'a.test.js', 'content' => 'test("a", () => {})']];

        return [
            'unknown language' => [['language' => 'cobol']],
            'no language' => [['language' => null]],
            'no files' => [['files' => []]],
            'a map instead of a list' => [['files' => ['sum.js' => 'x']]],
            'files with extra keys' => [['files' => [['path' => 'a.js', 'content' => 'x', 'exec' => true]]]],
            'a path with traversal' => [['files' => $file('../a.js')]],
            'a nested traversal' => [['files' => $file('a/../../b.js')]],
            'an absolute path' => [['files' => $file('/etc/a.js')]],
            'a hidden file' => [['files' => $file('.env.js')]],
            'a hidden folder' => [['files' => $file('.git/a.js')]],
            'backslashes' => [['files' => $file('a\\b.js')]],
            'a null byte in the path' => [['files' => $file("a\0.js")]],
            'spaces in the path' => [['files' => $file('my file.js')]],
            'a path that is too long' => [['files' => $file(str_repeat('a', 90) . '.js')]],
            'a php file' => [['files' => $file('a.php')]],
            'an html file' => [['files' => $file('index.html')]],
            'a ts file in a javascript exercise' => [['files' => $file('a.ts')]],
            'no extension' => [['files' => $file('Makefile')]],
            'a binary content' => [['files' => $file('a.js', "ab\0cd")]],
            'invalid UTF-8' => [['files' => $file('a.js', "\xff\xfe")]],
            'a file over 64 KB' => [['files' => $file('a.js', str_repeat('a', 64 * 1024 + 1))]],
            'more than 20 files' => [['files' => array_map(fn ($i) => ['path' => "f{$i}.js", 'content' => 'x'], range(1, 21))]],
            'more than 256 KB together' => [['files' => array_map(fn ($i) => ['path' => "f{$i}.js", 'content' => str_repeat('a', 60000)], range(1, 5))]],
            'the same path twice' => [['files' => [['path' => 'a.js', 'content' => 'x'], ['path' => 'A.js', 'content' => 'y']]]],
            'content that is not text' => [['files' => [['path' => 'a.js', 'content' => ['x']]]]],
            'no tests' => [['visible_tests' => []]],
            'a test file with the path of a starter file' => [['visible_tests' => [['path' => 'sum.js', 'content' => 'test("x", () => {})']]]],
            'a test file that is json' => [['visible_tests' => $file('a.json')]],
            'more than 10 test files' => [['visible_tests' => array_map(fn ($i) => ['path' => "t{$i}.test.js", 'content' => 'x'], range(1, 11))]],
            'a timeout that is too short' => [['timeout_ms' => 100]],
            'a timeout that is too long' => [['timeout_ms' => 60000]],
            'an unknown completion rule' => [['completion_rule' => 'never']],
            'a pass score of zero' => [['pass_score' => 0]],
            'a statement that is too long' => [['text' => str_repeat('a', 20001)]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidContent')]
    public function testInvalidContentIsRejectedAndNothingIsCreated(array $override): void
    {
        $this->postTopic($override + $this->exercise())->assertUnprocessable();

        $this->assertSame(0, CodeExerciseTopic::query()->count());
    }

    public function testAStudentCannotCreateAnExercise(): void
    {
        $this->postTopic($this->exercise(), $this->makeStudent())->assertForbidden();
        $this->assertSame(0, CodeExerciseTopic::query()->count());
    }
}
