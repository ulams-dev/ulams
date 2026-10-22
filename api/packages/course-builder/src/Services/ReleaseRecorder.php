<?php

namespace Ulams\CourseBuilder\Services;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;

/**
 * Writes the releases of a builder course (ADR 0192, plan docs/plans/course-versions-and-variants.md section 6.1).
 *
 * A release marks a version that learners got. It is written by the apply step, in the transaction that sets
 * `applied_version_id`, and never by hand. Applying the version that is already the latest release changes nothing for
 * learners and writes nothing. Undo and redo that re-apply an older version are releases too (trigger `restore`).
 */
final class ReleaseRecorder
{
    /**
     * Records the apply and moves the session to it: sets `course_id` and `applied_version_id` (plus any other session
     * attribute the caller passes, such as the status) and writes the release, all or nothing.
     *
     * @param array<string,mixed> $sessionAttributes
     * @param string|null $trigger one of Release::TRIGGERS; null derives it from the version
     */
    public function applied(Session $session, Version $version, int $courseId, array $sessionAttributes = [], ?string $trigger = null): ?Release
    {
        return DB::transaction(function () use ($session, $version, $courseId, $sessionAttributes, $trigger) {
            // the same lock that numbers versions: two applies of one session never take the same release number
            Session::query()->whereKey($session->id)->lockForUpdate()->first(['id']);
            $session->forceFill(['course_id' => $courseId, 'applied_version_id' => $version->id] + $sessionAttributes)->save();

            $previous = $this->latest($session->id);
            if ($previous !== null && $previous->version_id === $version->id && $previous->course_id === $courseId) {
                return null;
            }

            return $this->write($session, $version, $courseId, $previous, $trigger ?? $this->triggerFor($version, $previous));
        });
    }

    /**
     * The same for a variant (ADR 0192): the variant's own course, its own `applied_version_id` and its own release
     * numbers, in the transaction that moves the variant to the version. The caller passes the variant attributes
     * that belong to the apply (such as the status). Applying the version that is already the variant's latest release
     * for the same course changes nothing for learners and writes nothing.
     *
     * @param array<string,mixed> $variantAttributes
     * @param string|null $trigger one of Release::TRIGGERS; null derives it from the version
     */
    public function appliedVariant(Variant $variant, Version $version, int $courseId, array $variantAttributes = [], ?string $trigger = null): ?Release
    {
        return DB::transaction(function () use ($variant, $version, $courseId, $variantAttributes, $trigger) {
            Session::query()->whereKey($variant->session_id)->lockForUpdate()->first(['id']);
            $variant->forceFill(['course_id' => $courseId, 'applied_version_id' => $version->id] + $variantAttributes)->save();

            $previous = $this->latest($variant->session_id, $variant->id);
            if ($previous !== null && $previous->version_id === $version->id && $previous->course_id === $courseId) {
                return null;
            }

            return $this->write($variant->session, $version, $courseId, $previous, $trigger ?? $this->triggerFor($version, $previous), null, $variant->id);
        });
    }

    public function latest(string $sessionId, ?string $variantId = null): ?Release
    {
        return Release::query()->where('session_id', $sessionId)->where('variant_id', $variantId)->orderByDesc('number')->first();
    }

    /**
     * One release for a session that was applied before releases existed (trigger `generate`, released when its version
     * was approved). Idempotent: a session that has a release is left alone.
     */
    public function backfill(Session $session): ?Release
    {
        if ($session->course_id === null || $session->applied_version_id === null || $this->latest($session->id) !== null) {
            return null;
        }
        $version = Version::query()->where('session_id', $session->id)->find($session->applied_version_id);
        if ($version === null) {
            return null;
        }

        return DB::transaction(fn () => $this->write($session, $version, (int) $session->course_id, null, 'generate', $version->decided_at ?? $session->updated_at ?? now()));
    }

    /**
     * One release for a variant that was applied before its releases were recorded (CV-4 wrote none). Idempotent.
     */
    public function backfillVariant(Variant $variant): ?Release
    {
        if ($variant->course_id === null || $variant->applied_version_id === null || $this->latest($variant->session_id, $variant->id) !== null) {
            return null;
        }
        $version = Version::query()->where('session_id', $variant->session_id)->find($variant->applied_version_id);
        if ($version === null) {
            return null;
        }

        return DB::transaction(fn () => $this->write($variant->session, $version, (int) $variant->course_id, null, 'generate', $version->decided_at ?? $variant->updated_at ?? now(), $variant->id));
    }

    private function write(Session $session, Version $version, int $courseId, ?Release $previous, string $trigger, mixed $releasedAt = null, ?string $variantId = null): Release
    {
        $before = $previous !== null ? Version::query()->find($previous->version_id) : null;

        return Release::query()->create([
            'session_id' => $session->id,
            'variant_id' => $variantId,
            'number' => (int) Release::query()->where('session_id', $session->id)->when($variantId === null, fn ($q) => $q->whereNull('variant_id'), fn ($q) => $q->where('variant_id', $variantId))->max('number') + 1,
            'version_id' => $version->id,
            'previous_release_id' => $previous?->id,
            'course_id' => $courseId,
            'trigger' => $trigger,
            'source_revisions' => $version->source_revisions ?: null,
            'approved_by' => $version->decided_by,
            'summary' => self::summary($before?->document ?? [], $version->document),
            'released_at' => $releasedAt ?? now(),
        ]);
    }

    /** What started the change, read from the version when the caller does not know better. */
    private function triggerFor(Version $version, ?Release $previous): string
    {
        if ($previous !== null) {
            $was = Version::query()->find($previous->version_id);
            $again = Release::query()->where('session_id', $previous->session_id)->where('variant_id', $previous->variant_id)->where('version_id', $version->id)->exists();
            if ($again || ($was !== null && $version->number < $was->number)) {
                return 'restore';
            }
        }

        return match ($version->kind) {
            'outline', 'content' => 'generate',
            'patch' => 'chat',
            'update' => 'update',
            'import' => 'import',
            'restore' => 'restore',
            default => 'author',
        };
    }

    /**
     * Counts of what changed between two blueprint documents, for the release list. An empty `$from` is the first
     * release: everything is added.
     *
     * @return array{first:bool,counts:array{added:int,changed:int,removed:int},lessons:array{added:int,changed:int,removed:int},questions:array{added:int,changed:int,removed:int},citationsChanged:int,answerKeysChanged:int}
     */
    public static function summary(array $from, array $to): array
    {
        $changes = BlueprintDiff::compare($from, $to);
        $byType = fn (string $type) => BlueprintDiff::counts(array_values(array_filter($changes, fn ($c) => $c['type'] === $type)));
        $citations = 0;
        $questions = [];
        foreach ($changes as $c) {
            if ($c['kind'] !== 'changed') {
                continue;
            }
            foreach ($c['fields'] as $field) {
                if ($field['field'] === 'citations') {
                    $citations++;
                }
                // the correct flag lives on the option; the question it belongs to is its last ancestor
                if ($c['type'] === 'option' && $field['field'] === 'correct' && $c['ancestors'] !== []) {
                    $questions[end($c['ancestors'])] = true;
                }
            }
        }

        return [
            'first' => $from === [],
            'counts' => BlueprintDiff::counts($changes),
            'lessons' => $byType('lesson'),
            'questions' => $byType('question'),
            'citationsChanged' => $citations,
            'answerKeysChanged' => count($questions),
        ];
    }
}
