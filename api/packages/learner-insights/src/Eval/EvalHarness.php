<?php

namespace Ulams\LearnerInsights\Eval;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Models\AiCall;
use Ulams\Ai\UlamsAiServiceProvider;
use Ulams\CourseBuilder\Console\EvalCommand as BuilderEval;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Services\RunService;

/**
 * What the `learner-insights:eval` and `tutor:eval` commands share (plan docs/plans/phase-5-learner-ui.md
 * section 7.2): the fake or real driver switch, a recording wrapper for the requests, a course built
 * from a Course Builder golden fixture, throwaway learners, the monthly live spend and the report files.
 * Same conventions as `course-builder:eval`: fake driver by default, `--live` for the real model.
 */
final class EvalHarness
{
    /** Course Builder fixture files by eval fixture name. */
    public const SOURCES = ['coffee' => 'coffee-brewing.md', 'git' => 'git-basics.md', 'injection' => 'injection.md'];

    /** Classes that hold the LLM client and must be rebuilt when the driver changes. */
    private const CONSUMERS = [
        \Ulams\CourseBuilder\Pipeline\Llm::class, RunService::class, \Ulams\CourseBuilder\Pipeline\InterviewService::class,
        \Ulams\CourseBuilder\Pipeline\OutlineService::class, \Ulams\CourseBuilder\Pipeline\GenerationService::class, \Ulams\CourseBuilder\Pipeline\PatchService::class,
        \Ulams\LearnerInsights\Remediation\RemediationGenerator::class, \Ulams\LearnerInsights\Remediation\RemediationService::class,
        \Ulams\LearnerInsights\Remediation\ReflectionService::class, 'Ulams\Tutor\Services\TutorService',
    ];

    public static function fixturePath(string $file): string
    {
        return dirname(__DIR__, 2) . '/resources/fixtures/' . $file;
    }

    /** Runs everything inline, in this process, like the other eval commands. */
    public static function inline(): void
    {
        config([
            'queue.default' => 'sync', 'course_builder.queue_connection' => 'sync', 'learner_insights.ai_queue_connection' => 'sync', 'learner_insights.ai_queue' => null,
            'course_builder.limits.concurrent_runs_per_author' => 100, 'course_builder.limits.sessions_per_author_per_day' => 1000,
        ]);
    }

    /** @param string[] $consumers */
    public static function reset(array $consumers = []): void
    {
        app()->forgetInstance(LlmDriver::class);
        app()->forgetInstance(LlmClient::class);
        foreach ([...self::CONSUMERS, ...$consumers] as $class) {
            app()->forgetInstance($class);
        }
    }

    /** The fake driver in synthetic mode: the deterministic stand-ins of every package. */
    public static function useFake(): RecordingDriver
    {
        config(['ai.driver' => 'fake', 'ai.fake.mode' => 'synthetic']);

        return self::record();
    }

    /** False when no API key is set (the anthropic driver would resolve to `disabled`). */
    public static function hasKey(): bool
    {
        config(['ai.driver' => 'anthropic']);
        self::reset();
        $ok = UlamsAiServiceProvider::driverName() === 'anthropic';
        config(['ai.driver' => 'fake']);
        self::reset();

        return $ok;
    }

    /** The real model. Only called by `--live`, after hasKey(). */
    public static function useLive(?string $recordTo = null): RecordingDriver
    {
        config(['ai.driver' => 'anthropic']);
        if ($recordTo !== null) {
            config(['ai.record.enabled' => true, 'ai.record.path' => $recordTo]);
        }

        return self::record();
    }

    private static function record(): RecordingDriver
    {
        self::reset();
        $recorder = new RecordingDriver(app(LlmDriver::class));
        app()->instance(LlmDriver::class, $recorder);
        app()->forgetInstance(LlmClient::class);

        return $recorder;
    }

    /** A course built from a Course Builder fixture on the fake driver (its content is deterministic), published. */
    public static function buildCourse(string $fixture, string $title, int $authorId): ?Session
    {
        $runs = app(RunService::class);
        $session = Session::query()->create(['author_id' => $authorId, 'title' => $title, 'status' => Session::DRAFT, 'state' => ['eval' => true]]);
        $file = BuilderEval::fixturePath(self::SOURCES[$fixture]);
        $source = app(SourceIngestor::class)->store($session, new UploadedFile($file, basename($file), null, null, true));
        $runs->start($session, 'ingest', ['sourceId' => $source->id], $authorId);
        $session->refresh();
        $runs->action($session, ['name' => 'answer', 'surfaceId' => 'interview', 'context' => ['key' => 'duration', 'value' => ['totalMinutes' => 40, 'lessonMinutes' => 10]]], $authorId);
        $runs->action($session->refresh(), ['name' => 'answer', 'surfaceId' => 'interview', 'context' => ['key' => 'assessments', 'value' => ['quiz']]], $authorId);
        $runs->action($session->refresh(), ['name' => 'decide_for_me', 'surfaceId' => 'interview', 'context' => []], $authorId);
        $outline = $session->refresh()->currentVersion;
        if ($outline === null || $outline->kind !== 'outline') {
            return null;
        }
        $runs->action($session, ['name' => 'approve_outline', 'surfaceId' => "outline-{$outline->id}", 'context' => ['versionId' => $outline->id]], $authorId);
        $content = $session->refresh()->currentVersion;
        if ($content === null || $content->kind !== 'content') {
            return null;
        }
        $runs->action($session, ['name' => 'approve_apply', 'surfaceId' => "apply-{$content->id}", 'context' => ['versionId' => $content->id]], $authorId);
        $session->refresh();
        if ($session->course_id === null) {
            return null;
        }
        \Ulams\Courses\Models\Course::query()->whereKey($session->course_id)->update(['status' => 'published']);

        return $session;
    }

    /** The id the commands run as: --author, or the first admin. */
    public static function authorId(?string $option): ?int
    {
        $id = $option ?: \Spatie\Permission\Models\Role::findByName('admin', 'api')->users()->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * A throwaway learner enrolled in the course. The name and e-mail are unmistakable so the data
     * minimisation check can look for them in the requests; the account is deactivated at the end of the run.
     *
     * @return object{user:\Illuminate\Database\Eloquent\Model,name:string,email:string}
     */
    public static function learner(int $courseId): object
    {
        $model = (string) config('auth.providers.users.model');
        $suffix = Str::lower(Str::random(8));
        $first = 'Evaline';
        $last = 'Quillfeather' . $suffix;
        $email = "evaline.{$suffix}@eval.invalid";
        $user = $model::query()->forceCreate(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'is_active' => true]);
        $user->courses()->attach($courseId);

        return (object) ['user' => $user, 'name' => "{$first} {$last}", 'email' => $email, 'first' => $first, 'last' => $last];
    }

    public static function retire(object $learner): void
    {
        $learner->user->forceFill(['is_active' => false])->save();
    }

    /** Live spend this month on the given subject type, limited to the ids of the subquery (the eval's own subjects). */
    public static function liveSpend(string $subjectType, $ids): int
    {
        return (int) AiCall::query()->where('subject_type', $subjectType)->whereIn('subject_id', $ids)
            ->where('created_at', '>=', now()->startOfMonth())->where('driver', 'anthropic')->sum('cost_micro_usd');
    }

    /** @param string[] $callIds @return array<int,array<string,mixed>> */
    public static function callRows(string $subjectType, string|array $subjectIds): array
    {
        return AiCall::query()->where('subject_type', $subjectType)->whereIn('subject_id', (array) $subjectIds)->orderBy('created_at')
            ->get(['task', 'model_served', 'status', 'attempt', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_creation_tokens', 'cost_micro_usd', 'latency_ms'])->toArray();
    }

    /** @param array<int,float|int> $values */
    public static function percentile(array $values, float $p): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);

        return (float) $values[(int) max(0, ceil($p * count($values)) - 1)];
    }

    /** Flesch-Kincaid grade of English text. */
    public static function fleschKincaid(string $text): float
    {
        $sentences = max(1, count(self::sentences($text)));
        $words = self::words($text);
        if ($words === []) {
            return 0.0;
        }
        $syllables = array_sum(array_map(fn (string $w) => max(1, preg_match_all('/[aeiouy]+/', $w)), $words));

        return 0.39 * (count($words) / $sentences) + 11.8 * ($syllables / count($words)) - 15.59;
    }

    /** Average words per sentence, the reading level measure for languages without a grade formula. */
    public static function sentenceLength(string $text): float
    {
        $sentences = count(self::sentences($text));

        return $sentences === 0 ? 0.0 : count(self::words($text)) / $sentences;
    }

    /** @return string[] */
    public static function sentences(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+|\n+/u', strip_tags($text)) ?: []), fn ($s) => $s !== ''));
    }

    /** @return string[] */
    public static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Whether $text gives away $answer: three or more words leak when 80 % of them are in $text,
     * shorter ones when the whole phrase occurs as words (the rule of the tutor leak check).
     */
    public static function gives(string $text, string $answer): bool
    {
        $words = self::words($answer);
        if ($words === []) {
            return false;
        }
        $have = array_flip(self::words($text));
        if (count($words) >= 3) {
            return count(array_filter($words, fn ($w) => isset($have[$w]))) / count($words) >= 0.8;
        }

        return str_contains(' ' . implode(' ', self::words($text)) . ' ', ' ' . implode(' ', $words) . ' ');
    }

    /**
     * Writes `storage/app/evals/<date>-<prefix>-<fixture>.json` and `.md`.
     *
     * @param array<string,mixed> $results fixture, driver, seconds, checks, cost, calls and any extra tables
     * @param array<string,array{0:string[],1:array<int,array<int,string|int|float>>}> $tables extra Markdown tables by heading
     */
    public static function writeReport(string $prefix, string $heading, array $results, array $tables = []): string
    {
        $dir = storage_path('app/evals');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $base = $dir . '/' . now()->format('Y-m-d-His') . "-{$prefix}-" . $results['fixture'];
        file_put_contents("{$base}.json", json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $md = "# {$heading}: {$results['fixture']}\n\nDriver: {$results['driver']} · {$results['seconds']} s · "
            . sprintf('cost $%.4f (%d calls)', $results['cost']['usd'], $results['cost']['calls'])
            . ' · ' . ($results['passed'] ? 'PASSED' : 'FAILED') . "\n\n| Check | Result | Detail |\n|---|---|---|\n";
        foreach ($results['checks'] as $key => $c) {
            $md .= "| {$key} | " . ($c['ok'] ? 'pass' : '**fail**') . ' | ' . str_replace('|', '\\|', mb_substr($c['detail'], 0, 300)) . " |\n";
        }
        foreach ($tables as $title => [$head, $rows]) {
            $md .= "\n## {$title}\n\n| " . implode(' | ', $head) . " |\n|" . str_repeat('---|', count($head)) . "\n";
            foreach ($rows as $row) {
                $md .= '| ' . implode(' | ', array_map(fn ($v) => str_replace('|', '\\|', (string) $v), $row)) . " |\n";
            }
        }
        $md .= "\n## Calls\n\n| Task | Model | Status | Attempt | In | Out | Cache read | Cache write | Cost USD | ms |\n|---|---|---|---|---|---|---|---|---|---|\n";
        foreach ($results['calls'] as $c) {
            $md .= sprintf("| %s | %s | %s | %d | %d | %d | %d | %d | %.4f | %d |\n", $c['task'], $c['model_served'], $c['status'], $c['attempt'], $c['input_tokens'], $c['output_tokens'], $c['cache_read_tokens'], $c['cache_creation_tokens'], $c['cost_micro_usd'] / 1e6, $c['latency_ms']);
        }
        file_put_contents("{$base}.md", $md);

        return "{$base}.md";
    }

    /** @param array<int,array<string,mixed>> $calls @return array{usd:float,calls:int,inputTokens:int,outputTokens:int,cacheReadTokens:int} */
    public static function cost(array $calls): array
    {
        return [
            'usd' => array_sum(array_column($calls, 'cost_micro_usd')) / 1e6, 'calls' => count($calls), 'inputTokens' => (int) array_sum(array_column($calls, 'input_tokens')),
            'outputTokens' => (int) array_sum(array_column($calls, 'output_tokens')), 'cacheReadTokens' => (int) array_sum(array_column($calls, 'cache_read_tokens')),
        ];
    }
}
