<?php

namespace Ulams\CourseBuilder\CourseAsCode;

use Illuminate\Http\Request;
use Ulams\Auth\Support\TokenContext;

/**
 * Who may approve a proposed import (ADR 0160, plan 7.1). A person logged in to the studio, or a
 * CLI token a person created, may. An `agent` token or a request from an MCP client never may:
 * an agent proposes, the author approves. A `ci` token may only when the tenant allows it
 * (`course_as_code.ci_may_approve`, off by default), in which case the merged pull request is the review.
 */
final class ApprovalGuard
{
    /** @throws CourseAsCodeException 403 APPROVAL_REQUIRES_HUMAN */
    public function assertMayApprove(Request $request): void
    {
        $refuse = fn (string $why) => throw new CourseAsCodeException($why, 403, 'APPROVAL_REQUIRES_HUMAN');

        if (strtolower(trim((string) $request->header('X-Ulams-Client'))) === 'mcp') {
            $refuse('An MCP client can propose changes but only a person can approve them.');
        }
        $kind = self::tokenKind($request);
        if ($kind === null || $kind === 'cli') {
            return;
        }
        if ($kind === 'ci' && (bool) config('course_as_code.ci_may_approve', false)) {
            return;
        }
        $refuse(match ($kind) {
            'ci' => 'This tenant does not let CI tokens approve; approve the version in the studio or with a CLI login.',
            'agent' => 'An agent token can propose changes but only a person can approve them.',
            default => "A {$kind} token cannot approve course changes.",
        });
    }

    /** `cli | agent | ci | integration` for a scoped token, null for a login session. */
    public static function tokenKind(Request $request): ?string
    {
        return class_exists(TokenContext::class) ? TokenContext::resolve($request)?->meta->kind : null;
    }

    public static function tokenId(Request $request): ?string
    {
        return class_exists(TokenContext::class) ? TokenContext::resolve($request)?->tokenId : null;
    }
}
