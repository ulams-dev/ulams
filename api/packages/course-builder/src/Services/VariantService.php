<?php

namespace Ulams\CourseBuilder\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Exceptions\BuilderException;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Pipeline\BriefService;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;

/**
 * Variants of a builder course by level and role (ADR 0192, plan `course-versions-and-variants`): the branches of one
 * session. Creates them from the interview answer (or one at a time later), enforces the cap
 * (`COURSE_BUILDER_MAX_VARIANTS`), estimates what a run costs before it starts and describes them for the API.
 */
final class VariantService
{
    /** @return array<int,Variant> every variant of the session in picker order, retired ones last */
    public function all(Session $session): array
    {
        return Variant::query()->where('session_id', $session->id)->orderBy('position')->orderBy('created_at')->get()
            ->sortBy(fn (Variant $v) => $v->status === Variant::RETIRED ? 1 : 0)->values()->all();
    }

    /** The variants that count against the cap and take part in runs. */
    public function active(Session $session): int
    {
        return Variant::query()->where('session_id', $session->id)->where('status', '!=', Variant::RETIRED)->count();
    }

    /**
     * Creates the variants of the brief's audiences that do not exist yet (by key). Safe to call again.
     *
     * @return array<int,Variant>
     */
    public function fromBrief(Session $session): array
    {
        return $this->create($session, (array) ($session->brief['audiences'] ?? []));
    }

    /**
     * @param array<int,array{key?:string,label?:string,level?:?string,role?:?string,notes?:string}> $audiences
     *
     * @return array<int,Variant> the variants created (existing keys are skipped)
     *
     * @throws BuilderException the cap would be exceeded
     * @throws InvalidArgumentException an audience that is not valid
     */
    public function create(Session $session, array $audiences): array
    {
        $existing = Variant::query()->where('session_id', $session->id)->pluck('key')->all();
        $new = [];
        foreach ($audiences as $audience) {
            $level = isset($audience['level']) && $audience['level'] !== '' ? (string) $audience['level'] : null;
            $role = isset($audience['role']) && trim((string) $audience['role']) !== '' ? mb_substr(trim((string) $audience['role']), 0, 64) : null;
            if ($level !== null && !in_array($level, Variant::LEVELS, true)) {
                throw new InvalidArgumentException('A level is beginner, intermediate or expert.');
            }
            if ($level === null && $role === null) {
                throw new InvalidArgumentException('A variant needs a level, a role or both.');
            }
            $label = trim((string) ($audience['label'] ?? '')) ?: implode(', ', array_filter([$level !== null ? ucfirst($level) : null, $role]));
            $key = self::keyOf($audience);
            if ($key === '' || in_array($key, $existing, true) || isset($new[$key])) {
                continue;
            }
            $new[$key] = ['key' => $key, 'label' => mb_substr($label, 0, 120), 'level' => $level, 'role' => $role, 'notes' => mb_substr(trim((string) ($audience['notes'] ?? '')), 0, 600)];
        }
        $max = BriefService::maxVariants();
        if ($this->active($session) + count($new) > $max) {
            throw new BuilderException(sprintf('A course can have %d variants besides its base; this would make %d. Retire one, or ask an admin to raise COURSE_BUILDER_MAX_VARIANTS.', $max, $this->active($session) + count($new)), 422);
        }
        $made = [];
        $position = (int) Variant::query()->where('session_id', $session->id)->max('position');
        foreach ($new as $a) {
            $made[] = Variant::query()->create([
                'session_id' => $session->id,
                'key' => $a['key'],
                'label' => $a['label'],
                'level' => $a['level'],
                'role' => $a['role'],
                'audience_brief' => array_filter(['level' => $a['level'], 'role' => $a['role'], 'notes' => $a['notes'] !== '' ? $a['notes'] : null]),
                'status' => Variant::DRAFT,
                'position' => ++$position,
            ]);
        }

        return $made;
    }

    /** The key a variant of this audience gets: the given key, else the slug of its label (level and role). @param array<string,mixed> $audience */
    public static function keyOf(array $audience): string
    {
        $level = isset($audience['level']) && $audience['level'] !== '' ? (string) $audience['level'] : null;
        $role = isset($audience['role']) && trim((string) $audience['role']) !== '' ? mb_substr(trim((string) $audience['role']), 0, 64) : null;
        $label = trim((string) ($audience['label'] ?? '')) ?: implode(', ', array_filter([$level !== null ? ucfirst($level) : null, $role]));

        return (string) ($audience['key'] ?? '') !== '' ? Str::slug((string) $audience['key']) : Str::slug($label);
    }

    /** @param array{label?:string,position?:int,status?:string,notes?:string} $changes */
    public function update(Variant $variant, array $changes): Variant
    {
        if (isset($changes['label'])) {
            $variant->label = mb_substr(trim((string) $changes['label']), 0, 120) ?: $variant->label;
        }
        if (isset($changes['position'])) {
            $variant->position = max(0, min(1000, (int) $changes['position']));
        }
        if (isset($changes['notes'])) {
            $variant->audience_brief = ['notes' => mb_substr(trim((string) $changes['notes']), 0, 600)] + (array) $variant->audience_brief;
        }
        if (($changes['status'] ?? null) === Variant::RETIRED) {
            $this->retire($variant);
        }
        $variant->save();

        return $variant;
    }

    /** A retired variant is no longer offered; its LMS course (if any) stays, learners in it keep their progress. */
    public function retire(Variant $variant): void
    {
        $variant->status = Variant::RETIRED;
        $variant->save();
    }

    /**
     * What generating `$count` variants will cost about, shown before the run: the lessons of the base (the generated
     * ones, else the number the brief's duration asks for) times the cost of one lesson (this session's average of the
     * `lesson` calls when it has some, else the configured figure), plus the light outline call per variant. A variant
     * keeps some lessons unchanged, so this is an upper bound.
     *
     * @return array{variants:int,lessons:int,perLessonMicroUsd:int,estimateMicroUsd:int,basis:string}
     */
    public function estimate(Session $session, int $count): array
    {
        $doc = $session->currentVersion?->document;
        $lessons = $doc !== null ? Blueprint::stats($doc)['lessons'] : 0;
        $brief = (array) $session->brief;
        if ($lessons === 0) {
            $lessons = max(1, (int) round(((int) ($brief['totalMinutes'] ?? 60)) / max(1, (int) ($brief['lessonMinutes'] ?? 10))));
        }
        $row = AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->where('task', 'lesson')
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(cost_micro_usd),0) AS c')->first();
        $measured = (int) ($row->n ?? 0) > 0;
        $perLesson = $measured ? (int) round(((int) $row->c) / (int) $row->n) : (int) round(((float) config('course_builder.variants.lesson_estimate_usd', 0.06)) * 1000000);
        $outline = (int) round($perLesson / 4);

        return [
            'variants' => $count,
            'lessons' => $lessons,
            'perLessonMicroUsd' => $perLesson,
            'estimateMicroUsd' => $count * ($lessons * $perLesson + $outline),
            'basis' => $measured ? 'This session’s lesson calls' : 'An average lesson',
        ];
    }

    /** One line for the interview question and the review surface, e.g. "about 3 × the lesson cost; about $1.80". */
    public function estimateLine(Session $session, int $count): string
    {
        $e = $this->estimate($session, max(1, $count));

        return sprintf('Each extra version costs up to about %s (%d lessons at about %s); %s.', self::usd($e['estimateMicroUsd'] / max(1, $count)), $e['lessons'], self::usd($e['perLessonMicroUsd']), mb_strtolower($e['basis']));
    }

    public static function usd(float $micro): string
    {
        return '$' . number_format($micro / 1000000, 2, '.', '');
    }

    /**
     * @return array<string,mixed> one variant as the API and the studio describe it
     */
    public function describe(Variant $variant, ?Session $session = null): array
    {
        $session ??= $variant->session;
        $base = $session->currentVersion;
        $current = $variant->currentVersion;
        $drift = null;
        if ($variant->based_on_version_id !== null && $base !== null && $base->id !== $variant->based_on_version_id) {
            $was = \Ulams\CourseBuilder\Models\Version::query()->find($variant->based_on_version_id);
            $drift = $was !== null ? count(\Ulams\CourseBuilder\Blueprint\BlueprintDiff::compare($was->document, $base->document)) : null;
        }

        return array_filter([
            'id' => $variant->id,
            'key' => $variant->key,
            'label' => $variant->label,
            'level' => $variant->level,
            'role' => $variant->role,
            'status' => $variant->status,
            'position' => $variant->position,
            'courseId' => $variant->course_id,
            'currentVersionId' => $variant->current_version_id,
            'currentVersionStatus' => $current?->status,
            'currentVersionKind' => $current?->kind,
            'appliedVersionId' => $variant->applied_version_id,
            'basedOnVersionId' => $variant->based_on_version_id,
            'baseDrift' => $drift,
            'family' => $variant->course_id !== null ? app(CourseVariantServiceContract::class)->describe((int) $variant->course_id) : null,
        ], fn ($v) => $v !== null);
    }
}
