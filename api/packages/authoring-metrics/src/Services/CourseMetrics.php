<?php

namespace Ulams\AuthoringMetrics\Services;

use Illuminate\Support\Facades\DB;
use Ulams\AuthoringMetrics\Models\CourseMetric;
use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Provenance;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;

/**
 * The authoring metric of one course (ADR 0194), computed from what was recorded and stored in
 * `authoring_course_metrics`. Every figure has a definition here and a `method_version`; when a definition
 * changes the version changes, so an export says which definitions produced a number.
 *
 *  - generation time: wall time of the stages of runs `outline`, `generate`, `apply`, `sync`; AI edit time: runs
 *    `patch`; interview time: runs `interview` (the author answering, shown on its own line). Runs `ingest` and the
 *    rest are in the stage list but in no total. Model time is the summed latency of the calls, for comparison.
 *  - cost: `ai_calls.cost_micro_usd` whose subject resolves to the course (see `SubjectResolvers`). Failed calls count,
 *    because they were paid for; their number is kept. No re-pricing.
 *  - counts and classes: the applied blueprint; `Provenance::classify` per module, lesson, block and question.
 */
class CourseMetrics
{
    public const METHOD_VERSION = 1;

    /** Runs whose stage time is machine time the author waited for. */
    public const GENERATION_KINDS = ['outline', 'generate', 'apply', 'sync'];

    public function __construct(private readonly SubjectResolvers $resolvers)
    {
    }

    /** The Course Builder session that produced the course, or null when it was not built with the builder. */
    public function sessionFor(int $courseId): ?Session
    {
        return Session::query()->where('course_id', $courseId)->orderByDesc('created_at')->first();
    }

    /** Computes and stores the snapshot; null when the course has no builder session. */
    public function recompute(int $courseId): ?CourseMetric
    {
        $session = $this->sessionFor($courseId);
        if ($session === null) {
            return null;
        }
        $values = ['session_id' => $session->id, 'method_version' => self::METHOD_VERSION, 'computed_at' => now()]
            + $this->time($session) + $this->cost($session) + $this->counts($session);

        CourseMetric::query()->updateOrCreate(['course_id' => $courseId], $values);

        return CourseMetric::query()->findOrFail($courseId);
    }

    /** The stored snapshot, computed on first read. */
    public function snapshot(int $courseId): ?CourseMetric
    {
        return CourseMetric::query()->find($courseId) ?? $this->recompute($courseId);
    }

    /** @return array<string,string> */
    private function time(Session $session): array
    {
        $by = StageTiming::query()->where('session_id', $session->id)->whereNotNull('seconds')
            ->selectRaw('run_kind, sum(seconds) as seconds')->groupBy('run_kind')->pluck('seconds', 'run_kind')->map(fn ($s) => (float) $s)->all();
        $sum = fn (array $kinds) => array_sum(array_intersect_key($by, array_flip($kinds)));
        $model = (int) DB::table('ai_calls')->where(fn ($q) => $this->forCourse($q, $session, null))->sum('latency_ms');

        return [
            'generation_seconds' => $this->secs($sum(self::GENERATION_KINDS)),
            'ai_edit_seconds' => $this->secs($sum(['patch'])),
            'interview_seconds' => $this->secs($sum(['interview'])),
            'model_seconds' => $this->secs($model / 1000),
        ];
    }

    /** @return array<string,int> */
    private function cost(Session $session): array
    {
        $out = ['cost_build_micro_usd' => 0, 'cost_maintenance_micro_usd' => 0, 'llm_calls' => 0, 'llm_calls_failed' => 0];
        foreach ([SubjectResolvers::BUILD => 'cost_build_micro_usd', SubjectResolvers::MAINTENANCE => 'cost_maintenance_micro_usd'] as $bucket => $column) {
            $row = DB::table('ai_calls')->where(fn ($q) => $this->forCourse($q, $session, $bucket))
                ->selectRaw("coalesce(sum(cost_micro_usd), 0) as cost, count(*) as calls, coalesce(sum(case when status <> 'ok' then 1 else 0 end), 0) as failed")->first();
            $out[$column] = (int) $row->cost;
            $out['llm_calls'] += (int) $row->calls;
            $out['llm_calls_failed'] += (int) $row->failed;
        }

        return $out;
    }

    /** Restricts an `ai_calls` query to the subjects that resolve to the session's course (all buckets when null). */
    private function forCourse($query, Session $session, ?string $bucket): void
    {
        $matched = false;
        foreach ($this->resolvers->all() as $type => $resolver) {
            if ($bucket !== null && $resolver->bucket() !== $bucket) {
                continue;
            }
            $ids = $resolver->subjectIds([$session->id]);
            if ($ids === []) {
                continue;
            }
            $matched = true;
            $query->orWhere(fn ($q) => $q->where('subject_type', $type)->whereIn('subject_id', $ids));
        }
        if (!$matched) {
            $query->whereRaw('1 = 0');
        }
    }

    /** @return array<string,int> */
    private function counts(Session $session): array
    {
        $zero = ['lessons' => 0, 'questions' => 0, 'blocks' => 0, 'cited_blocks' => 0, 'ai_elements' => 0, 'ai_accepted_unchanged' => 0,
            'ai_edited_by_author' => 0, 'ai_revised_on_request' => 0, 'ai_unknown' => 0, 'author_elements' => 0, 'learner_minutes_estimate' => 0];
        $version = Version::query()->find($session->applied_version_id ?? $session->current_version_id);
        if ($version === null) {
            return $zero;
        }
        $doc = $version->document;
        $stats = Blueprint::stats($doc);
        $patched = array_flip(Version::query()->where('session_id', $session->id)->where('kind', 'patch')->where('status', Version::APPROVED)
            ->whereNotNull('element_id')->pluck('element_id')->all());

        $classes = [Provenance::ACCEPTED_UNCHANGED => 0, Provenance::REVISED_ON_REQUEST => 0, Provenance::EDITED_BY_AUTHOR => 0, Provenance::AUTHOR_WRITTEN => 0, Provenance::UNKNOWN => 0];
        $cited = 0;
        foreach (self::elements($doc) as [$type, $element]) {
            $classes[Provenance::classify($element, isset($patched[$element['id'] ?? '']))]++;
            if ($type === 'block' && ($element['citations'] ?? []) !== []) {
                $cited++;
            }
        }

        return [
            'lessons' => $stats['lessons'], 'questions' => $stats['questions'], 'blocks' => $stats['blocks'], 'cited_blocks' => $cited,
            'ai_elements' => $classes[Provenance::ACCEPTED_UNCHANGED] + $classes[Provenance::REVISED_ON_REQUEST] + $classes[Provenance::EDITED_BY_AUTHOR] + $classes[Provenance::UNKNOWN],
            'ai_accepted_unchanged' => $classes[Provenance::ACCEPTED_UNCHANGED],
            'ai_edited_by_author' => $classes[Provenance::EDITED_BY_AUTHOR],
            'ai_revised_on_request' => $classes[Provenance::REVISED_ON_REQUEST],
            'ai_unknown' => $classes[Provenance::UNKNOWN],
            'author_elements' => $classes[Provenance::AUTHOR_WRITTEN],
            'learner_minutes_estimate' => $stats['minutes'],
        ];
    }

    /**
     * Modules, lessons, blocks and quiz questions (also the final test), the elements `Provenance` classifies.
     *
     * @return \Generator<int,array{0:string,1:array}>
     */
    public static function elements(array $doc): \Generator
    {
        foreach ($doc['modules'] ?? [] as $module) {
            yield ['module', $module];
            foreach ($module['lessons'] ?? [] as $lesson) {
                yield ['lesson', $lesson];
                foreach ($lesson['blocks'] ?? [] as $block) {
                    yield ['block', $block];
                }
                foreach ($lesson['quiz']['questions'] ?? [] as $question) {
                    yield ['question', $question];
                }
            }
        }
        foreach ($doc['finalTest']['questions'] ?? [] as $question) {
            yield ['question', $question];
        }
    }

    private function secs(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}
