<?php

namespace Ulams\CourseBuilder\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Policies\SessionPolicy;

/**
 * Every endpoint resolves its session first (directly or through a source, fragment, version or
 * run of it) and checks the policy: unknown ids are 404, someone else's session is 403, a user
 * without `course_builder_use` is 403. Ids from another tenant never resolve (database per tenant).
 */
trait ResolvesSessions
{
    protected function sessionFor(Request $request, ?string $id, string $ability = 'view'): Session
    {
        $session = $id !== null && preg_match('/^[0-9a-z]{26}$/i', $id) ? Session::query()->find(strtolower($id)) : null;
        if ($session === null) {
            throw new NotFoundHttpException('Session not found.');
        }
        $policy = new SessionPolicy();
        if (!$policy->{$ability}($request->user(), $session)) {
            throw new AccessDeniedHttpException('This builder session belongs to another author.');
        }

        return $session;
    }

    protected function assertCanUse(Request $request): void
    {
        if (!(new SessionPolicy())->use($request->user())) {
            throw new AccessDeniedHttpException('You do not have access to the Course Builder.');
        }
    }
}
