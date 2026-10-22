<?php

namespace Ulams\CourseAccess\Support;

use Closure;

/**
 * Names who is granting course access while a block of code runs, so the entitlements package (ADR 0130) can record
 * the right source (`lti`, `compliance`, `admin`) for every `course_user` row written through `CourseAccessService`.
 * Without a context a grant counts as `admin`. Contexts nest; the innermost wins and is always restored.
 */
final class AccessSourceContext
{
    /** @var list<array{type: string, ref: array<string,mixed>}> */
    private static array $stack = [];

    /**
     * @template T
     *
     * @param array<string,mixed> $ref free-form details stored in the entitlement's `source_ref`
     * @param Closure(): T $callback
     * @return T
     */
    public static function as(string $type, Closure $callback, array $ref = []): mixed
    {
        self::$stack[] = ['type' => $type, 'ref' => $ref];
        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }

    /** @return array{type: string, ref: array<string,mixed>}|null */
    public static function current(): ?array
    {
        return self::$stack === [] ? null : self::$stack[array_key_last(self::$stack)];
    }
}
