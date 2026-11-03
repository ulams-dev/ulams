<?php

namespace Ulams\CourseBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Models\AiCall;
use Ulams\Ai\UlamsAiServiceProvider;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\CourseBuilder\Services\RunService;
use Ulams\CourseBuilder\StyleProfile\StyleCheck;
use Ulams\CourseBuilder\StyleProfile\StyleProfileService;

/**
 * Evaluates the style profile feature (ADR 0196) on a short original style guide and a source: how much of the guide the
 * extraction finds per rule, whether every value cites a passage of the guide, and how often a course written with the
 * approved profile follows each rule, with the cost. Fake driver by default; `--live` calls the real model (costs money,
 * capped like the builder eval by COURSE_BUILDER_EVAL_MONTHLY_USD).
 *
 * It never touches the academy's approved profile: the profile it approves is a named `brief` profile that is removed again
 * at the end, and its sessions are marked as evals.
 */
class StyleEvalCommand extends Command
{
    protected $signature = 'course-builder:eval-style
        {--live : call the real model}
        {--author= : user id that owns the eval sessions (default: the first admin)}
        {--min-recall=0.8 : share of the guide\'s values the extraction must find, per rule}
        {--min-adherence=0.8 : share of elements that must follow each rule (banned phrases, terminology, images; reading level and sentence length are reported only)}';

    protected $description = 'Evaluate style profiles: extraction recall and citations, adherence of generated courses, cost (fake driver by default, --live for the real model)';

    public function handle(): int
    {
        config(['queue.default' => 'sync', 'course_builder.queue_connection' => 'sync', 'course_builder.limits.concurrent_runs_per_author' => 100, 'course_builder.limits.sessions_per_author_per_day' => 1000]);
        if ($this->option('live')) {
            config(['ai.driver' => 'anthropic']);
            if (UlamsAiServiceProvider::driverName() !== 'anthropic') {
                $this->error('No API key: set ANTHROPIC_API_KEY (or ANTROPHIC_API_KEY) for --live.');

                return self::FAILURE;
            }
            $spent = (int) AiCall::query()->whereIn('task', ['style_profile_extract', 'outline', 'lesson', 'quiz', 'metadata', 'interview', 'grounding'])->where('driver', 'anthropic')
                ->where('created_at', '>=', now()->startOfMonth())->whereIn('subject_id', Session::query()->where('state->eval', true)->select('id'))->sum('cost_micro_usd');
            if ($spent >= (int) round(((float) config('course_builder.limits.eval_monthly_usd', 20)) * 1000000)) {
                $this->error('The monthly eval spend cap is reached (COURSE_BUILDER_EVAL_MONTHLY_USD).');

                return self::FAILURE;
            }
        } elseif (config('ai.driver') !== 'fake') {
            config(['ai.driver' => 'fake']);
        }
        app()->forgetInstance(LlmDriver::class);
        app()->forgetInstance(LlmClient::class);
        foreach ([Llm::class, RunService::class, StyleProfileService::class, \Ulams\CourseBuilder\StyleProfile\StyleProfileExtractor::class, \Ulams\CourseBuilder\Pipeline\InterviewService::class, \Ulams\CourseBuilder\Pipeline\OutlineService::class,
            \Ulams\CourseBuilder\Pipeline\GenerationService::class, \Ulams\CourseBuilder\Pipeline\PatchService::class] as $service) {
            app()->forgetInstance($service);
        }
        $authorId = (int) ($this->option('author') ?: \Spatie\Permission\Models\Role::findByName('admin', 'api')->users()->value('id'));
        $author = $authorId ? app(config('auth.providers.users.model'))->newQuery()->find($authorId) : null;
        if ($author === null) {
            $this->error('No author: pass --author=<user id>.');

            return self::FAILURE;
        }

        $report = $this->evaluate($author);

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string,mixed> */
    private function evaluate($author): array
    {
        $started = microtime(true);
        $results = ['fixture' => 'style', 'checks' => [], 'rates' => []];
        $check = function (string $key, bool $ok, string $detail = '') use (&$results) {
            $results['checks'][$key] = ['ok' => $ok, 'detail' => $detail];
            $this->line(sprintf('  %s %s %s', $ok ? 'PASS' : 'FAIL', $key, $detail));
        };
        $minRecall = (float) $this->option('min-recall');
        $minAdherence = (float) $this->option('min-adherence');
        $guide = EvalCommand::fixturePath('style-guide.md');
        $expected = (array) json_decode((string) file_get_contents(EvalCommand::fixturePath('style-guide.expected.json')), true);

        $this->info('== style guide');
        $service = app(StyleProfileService::class);
        $key = 'eval-style-' . now()->format('YmdHis');
        $profile = $service->createFromUpload($author, new UploadedFile($guide, basename($guide), null, null, true), 'Eval style guide', 'brief', $key);
        $values = (array) $profile->profile;

        // recall per rule: the share of the guide's values the extraction found
        $recall = self::recall($expected, $values);
        foreach ($recall as $rule => [$found, $total]) {
            $rate = $total === 0 ? 1.0 : $found / $total;
            $results['rates']["recall.{$rule}"] = round($rate, 3);
            $check("extract.recall.{$rule}", $rate >= $minRecall, sprintf('%d of %d (%.0f%%)', $found, $total, $rate * 100));
        }
        // every value cites a passage of the guide that exists
        [$cited, $totalValues] = self::citationCoverage($values, $profile);
        $results['rates']['citation_coverage'] = $totalValues === 0 ? 0.0 : round($cited / $totalValues, 3);
        $check('extract.citation_coverage', $totalValues > 0 && $cited === $totalValues, "{$cited} of {$totalValues} values cite a passage of the guide");
        $check('extract.blocking_is_the_authors', ($values['banned']['blocking'] ?? false) === false);

        // a course written with the approved profile
        $this->info('== course written with the profile');
        $admin = \Spatie\Permission\Models\Role::findByName('admin', 'api')->users()->first() ?? $author;
        $profile = $service->approve($profile->refresh(), $admin);
        $runs = app(RunService::class);
        $session = Session::query()->create(['author_id' => $author->getKey(), 'title' => 'Eval: style', 'status' => Session::DRAFT, 'state' => ['eval' => true]]);
        $source = EvalCommand::fixturePath('coffee-brewing.md');
        $stored = app(SourceIngestor::class)->store($session, new UploadedFile($source, basename($source), null, null, true));
        $runs->start($session, 'ingest', ['sourceId' => $stored->id], (int) $author->getKey());
        $session->refresh();
        $session->brief = array_replace((array) $session->brief, ['styleProfileId' => $profile->id]);
        $session->save();
        $runs->action($session->refresh(), ['name' => 'answer', 'surfaceId' => 'interview', 'context' => ['key' => 'duration', 'value' => ['totalMinutes' => 30, 'lessonMinutes' => 10]]], (int) $author->getKey());
        $runs->action($session->refresh(), ['name' => 'answer', 'surfaceId' => 'interview', 'context' => ['key' => 'assessments', 'value' => ['quiz']]], (int) $author->getKey());
        $runs->action($session->refresh(), ['name' => 'decide_for_me', 'surfaceId' => 'interview', 'context' => []], (int) $author->getKey());
        $session->refresh();
        $outline = $session->currentVersion;
        if ($outline === null || $outline->kind !== 'outline') {
            $check('generation.outline', false, 'no outline');

            return $this->finish($session, $profile, $results, $started);
        }
        $runs->action($session, ['name' => 'approve_outline', 'surfaceId' => "outline-{$outline->id}", 'context' => ['versionId' => $outline->id]], (int) $author->getKey());
        $content = $session->refresh()->currentVersion;
        if ($content === null || $content->kind !== 'content') {
            $check('generation.content', false, 'no lessons');

            return $this->finish($session, $profile, $results, $started);
        }
        $sent = AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'lesson')->count();
        $check('generation.lessons', $sent > 0, "{$sent} lesson calls");

        $doc = $content->document;
        $report = (new StyleCheck())->run($doc, $profile, 'en');
        $units = count(StyleCheck::units($doc));
        $by = fn (array $findings, array $rules) => count(array_unique(array_map(fn ($f) => $f['elementId'], array_filter($findings, fn ($f) => in_array($f['rule'], $rules, true)))));
        $lessons = max(1, iterator_count(Blueprint::lessons($doc)));
        $adherence = [
            'banned' => 1 - $by([...$report['blocking'], ...$report['warnings']], ['banned']) / max(1, $units),
            'terminology' => 1 - $by($report['warnings'], ['terminology', 'casing']) / max(1, $units),
            'images' => 1 - $by($report['warnings'], ['images']) / max(1, $units),
            'reading_level' => 1 - $by($report['hints'], ['reading_level']) / $lessons,
            'sentence_length' => 1 - $by($report['hints'], ['sentence_length']) / $lessons,
        ];
        foreach ($adherence as $rule => $rate) {
            $results['rates']["adherence.{$rule}"] = round($rate, 3);
            // reading level and sentence length are hints with approximate formulas: reported, never scored
            $hint = in_array($rule, ['reading_level', 'sentence_length'], true);
            $check("adherence.{$rule}", $hint || $rate >= $minAdherence, sprintf('%s%.0f%% of %s follow it', $hint ? '(hint, not scored) ' : '', $rate * 100, $hint ? "{$lessons} lessons" : "{$units} elements"));
        }
        $check('adherence.blocking_findings', $report['blocking'] === [], count($report['blocking']) . ' blocking findings (the profile does not block, so none expected)');

        return $this->finish($session, $profile, $results, $started);
    }

    /**
     * @param array<string,mixed> $expected
     * @param array<string,mixed> $values
     * @return array<string,array{0:int,1:int}> rule => [found, expected]
     */
    public static function recall(array $expected, array $values): array
    {
        $count = function (array $want, array $have): array {
            $want = array_map('strval', $want);

            return [count(array_intersect($want, array_map('strval', $have))), count($want)];
        };
        $voice = (array) ($values['voice'] ?? []);
        $voiceFound = 0;
        foreach ((array) $expected['voice'] as $k => $v) {
            $voiceFound += ($voice[$k] ?? null) === $v ? 1 : 0;
        }
        $reading = [];
        foreach ((array) ($values['readingLevel'] ?? []) as $r) {
            $reading[] = $r['language'] . ':' . $r['level'];
        }
        $theme = (array) ($values['theme'] ?? []);
        $themeFound = 0;
        $themeTotal = 0;
        foreach ((array) $expected['theme'] as $k => $v) {
            $themeTotal++;
            $themeFound += ($theme[$k] ?? null) === $v ? 1 : 0;
        }
        $imagery = (array) ($values['imagery'] ?? []);
        $imageryFound = 0;
        foreach ((array) $expected['imagery'] as $k => $v) {
            $imageryFound += ($imagery[$k] ?? null) === $v ? 1 : 0;
        }

        return [
            'voice' => [$voiceFound, count((array) $expected['voice'])],
            'readingLevel' => $count(array_map(fn ($l, $v) => "{$l}:{$v}", array_keys((array) $expected['readingLevel']), (array) $expected['readingLevel']), $reading),
            'terminology' => $count((array) $expected['terminology'], array_column((array) ($values['terminology'] ?? []), 'use')),
            'banned' => $count((array) $expected['banned'], array_column((array) ($values['banned']['phrases'] ?? []), 'phrase')),
            'components' => [
                count(array_intersect((array) $expected['components']['preferred'], (array) ($values['components']['preferred'] ?? []))) + count(array_intersect((array) $expected['components']['disallowed'], (array) ($values['components']['disallowed'] ?? []))),
                count((array) $expected['components']['preferred']) + count((array) $expected['components']['disallowed']),
            ],
            'imagery' => [$imageryFound, count((array) $expected['imagery'])],
            'theme' => [$themeFound, $themeTotal],
            'examples' => [min((int) $expected['examples'], count((array) ($values['examples'] ?? []))), (int) $expected['examples']],
        ];
    }

    /** @return array{0:int,1:int} values that cite a passage of the guide that exists, and all values */
    public static function citationCoverage(array $values, StyleProfile $profile): array
    {
        $known = array_fill_keys(\Ulams\CourseBuilder\Models\Fragment::query()->whereIn('source_id', \Ulams\CourseBuilder\Models\Source::query()->where('session_id', $profile->session_id)->select('id'))->pluck('id')->all(), true);
        $ok = 0;
        $total = 0;
        $visit = function (array $node) use (&$visit, &$ok, &$total, $known): void {
            if (array_key_exists('citations', $node)) {
                $total++;
                $ok += $node['citations'] !== [] && count(array_filter($node['citations'], fn ($c) => !isset($known[$c]))) === 0 ? 1 : 0;

                return;
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $visit($child);
                }
            }
        };
        $visit(array_diff_key($values, ['schemaVersion' => 1]));

        return [$ok, $total];
    }

    /** @param array<string,mixed> $results @return array<string,mixed> */
    private function finish(Session $session, StyleProfile $profile, array $results, float $started): array
    {
        $extraction = AiCall::query()->forSubject(StyleProfile::SUBJECT_TYPE, $profile->id)->get();
        $cost = Llm::cost($session);
        $results['cost'] = ['extractionMicroUsd' => (int) $extraction->sum('cost_micro_usd'), 'courseMicroUsd' => $cost['usedMicroUsd'], 'totalMicroUsd' => (int) $extraction->sum('cost_micro_usd') + $cost['usedMicroUsd'], 'calls' => $extraction->count() + $cost['calls']];
        $results['seconds'] = round(microtime(true) - $started, 1);
        $results['passed'] = $results['checks'] !== [] && collect($results['checks'])->every(fn ($c) => $c['ok']);
        $results['driver'] = UlamsAiServiceProvider::driverName();
        $this->line(sprintf('  cost $%.4f (extraction $%.4f), %d calls, %ss, %s', $results['cost']['totalMicroUsd'] / 1e6, $results['cost']['extractionMicroUsd'] / 1e6, $results['cost']['calls'], $results['seconds'], $results['passed'] ? 'PASSED' : 'FAILED'));

        $dir = storage_path('app/evals');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $base = $dir . '/' . now()->format('Y-m-d-His') . '-style';
        file_put_contents("{$base}.json", json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $md = "# Style profile eval\n\nDriver: {$results['driver']} · {$results['seconds']} s · " . sprintf('cost $%.4f', $results['cost']['totalMicroUsd'] / 1e6) . "\n\n| Check | Result | Detail |\n|---|---|---|\n";
        foreach ($results['checks'] as $key => $c) {
            $md .= "| {$key} | " . ($c['ok'] ? 'pass' : '**fail**') . ' | ' . str_replace('|', '\\|', mb_substr($c['detail'], 0, 300)) . " |\n";
        }
        file_put_contents("{$base}.md", $md);
        $this->line("  report: {$base}.md");

        // the eval leaves no profile behind: the named profile it approved goes with its holder session; the academy default was never touched
        $holder = $profile->session_id;
        $profile->delete();
        if ($holder !== null) {
            Session::withTrashed()->where('id', $holder)->forceDelete();
        }

        return $results;
    }
}
