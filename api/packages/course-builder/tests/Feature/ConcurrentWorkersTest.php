<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * Several `queue:work` processes on the database connection against one generation run: the run must
 * reach `finished` with every stage opened exactly once. Real processes need committed rows, so this
 * test does not roll back; it deletes what it created.
 *
 * @requires extension pcntl
 */
class ConcurrentWorkersTest extends TestCase
{
    /** @var array<int,string> */
    private array $sessionIds = [];

    private array $userIds = [];

    public function beginDatabaseTransaction()
    {
        // workers are separate processes: the rows must be visible to them
    }

    protected function tearDown(): void
    {
        DB::table('jobs')->delete();
        foreach ($this->sessionIds as $id) {
            Session::query()->whereKey($id)->delete();
        }
        if ($this->userIds !== []) {
            DB::table('model_has_roles')->whereIn('model_id', $this->userIds)->delete();
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    public static function workerCounts(): array
    {
        return [[1], [2], [3], [4]];
    }

    /** @dataProvider workerCounts */
    #[\PHPUnit\Framework\Attributes\DataProvider('workerCounts')]
    public function testSeveralDatabaseWorkersFinishOneGenerationRun(int $workers): void
    {
        $author = $this->author();
        $this->userIds[] = $author->getKey();
        $session = $this->newSession($author);
        $this->sessionIds[] = $session->id;
        $this->actingAs($author, 'api')
            ->post("/api/admin/course-builder/sessions/{$session->id}/sources", ['file' => $this->fixture('coffee-brewing.md')])->assertStatus(202);
        $session->refresh();
        $this->action($author, $session, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 60, 'lessonMinutes' => 8]])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'assessments', 'value' => ['quiz', 'final']])->assertStatus(202);
        $this->action($author, $session, 'decide_for_me', 'interview')->assertStatus(202);
        $session->refresh();
        $outline = $session->current_version_id;
        $this->assertSame(Session::OUTLINE_REVIEW, $session->status);

        // from here on the generation steps go to the database queue
        config(['queue.default' => 'database', 'course_builder.queue_connection' => 'database', 'course_builder.limits.lesson_concurrency' => 4]);
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);
        $run = Run::query()->where('session_id', $session->id)->where('kind', 'generate')->firstOrFail();

        $this->runWorkers($workers, $run->id, 60);

        $run->refresh();
        $debug = json_encode([
            'run' => $run->only(['status', 'stage']),
            'steps' => Step::query()->where('run_id', $run->id)->get(['key', 'status', 'attempts'])->map(fn ($s) => "{$s->key}={$s->status}/{$s->attempts}")->all(),
            'jobs' => DB::table('jobs')->count(),
        ]);
        $this->assertSame('finished', $run->status, $debug);
        $this->assertSame([], Step::query()->where('run_id', $run->id)->where('status', '!=', 'done')->pluck('key')->all(), $debug);
        $this->assertSame(Session::APPLY_REVIEW, $session->refresh()->status);
        $this->assertSame(1, Event::query()->where('run_id', $run->id)->where('type', 'RUN_FINISHED')->count());
        foreach (['lessons', 'grounding', 'quizzes', 'metadata'] as $stage) {
            $this->assertSame(1, Event::query()->where('run_id', $run->id)->where('type', 'STEP_STARTED')->get()->filter(fn ($e) => $e->payload['stepName'] === $stage)->count(), "stage {$stage} opened once");
            $this->assertSame(1, Event::query()->where('run_id', $run->id)->where('type', 'STEP_FINISHED')->get()->filter(fn ($e) => $e->payload['stepName'] === $stage)->count(), "stage {$stage} finished once");
        }
    }

    /** Forks `$workers` queue workers that pull jobs until the run is over or the deadline passes. */
    private function runWorkers(int $workers, string $runId, int $seconds): void
    {
        DB::disconnect();
        $pids = [];
        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::reconnect();
                $deadline = microtime(true) + $seconds;
                while (microtime(true) < $deadline) {
                    $status = Run::query()->whereKey($runId)->value('status');
                    $left = DB::table('jobs')->count();
                    if (in_array($status, ['finished', 'failed', 'cancelled', 'needs_attention'], true) && $left === 0) {
                        break;
                    }
                    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
                    usleep(random_int(5, 40) * 1000);
                }
                posix_kill(getmypid(), SIGKILL);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();
    }
}
