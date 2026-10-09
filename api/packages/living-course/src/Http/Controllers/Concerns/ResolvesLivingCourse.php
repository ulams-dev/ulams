<?php

namespace Ulams\LivingCourse\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Policies\LivingCoursePolicy;

/**
 * Every endpoint resolves its builder session first (directly or through a source, connection,
 * revision, proposal, item or audit row of it) and checks the policy: unknown ids are 404, someone
 * else's session is 403, a user without `course_builder_use` is 403. Ids from another tenant never
 * resolve (database per tenant).
 */
trait ResolvesLivingCourse
{
    protected static function isUlid(?string $id): bool
    {
        return $id !== null && (bool) preg_match('/^[0-9a-z]{26}$/i', $id);
    }

    /** @param 'view'|'act' $ability */
    protected function sessionFor(Request $request, ?string $id, string $ability = 'view'): Session
    {
        $session = self::isUlid($id) ? Session::query()->find(strtolower((string) $id)) : null;

        return $this->authorise($request, $session, $ability);
    }

    protected function authorise(Request $request, ?Session $session, string $ability): Session
    {
        if ($session === null) {
            throw new NotFoundHttpException('Session not found.');
        }
        if (!(new LivingCoursePolicy())->{$ability}($request->user(), $session)) {
            throw new AccessDeniedHttpException($ability === 'act'
                ? 'You cannot change the sources of this course.'
                : 'This builder session belongs to another author.');
        }

        return $session;
    }

    /** @return array{0:Session,1:Source} */
    protected function sourceFor(Request $request, ?string $id, string $ability = 'view'): array
    {
        $source = self::isUlid($id) ? Source::query()->find(strtolower((string) $id)) : null;
        if ($source === null) {
            throw new NotFoundHttpException('Source not found.');
        }

        return [$this->sessionFor($request, $source->session_id, $ability), $source];
    }

    /** @return array{0:Session,1:Connection} */
    protected function connectionFor(Request $request, ?string $id, string $ability = 'view'): array
    {
        $connection = self::isUlid($id) ? Connection::query()->find(strtolower((string) $id)) : null;
        if ($connection === null) {
            throw new NotFoundHttpException('Connection not found.');
        }

        return [$this->sessionFor($request, $connection->session_id, $ability), $connection];
    }

    /** @return array{0:Session,1:Revision} */
    protected function revisionFor(Request $request, ?string $id, string $ability = 'view'): array
    {
        $revision = self::isUlid($id) ? Revision::query()->find(strtolower((string) $id)) : null;
        $source = $revision?->source;
        if ($revision === null || $source === null) {
            throw new NotFoundHttpException('Revision not found.');
        }

        return [$this->sessionFor($request, $source->session_id, $ability), $revision];
    }

    protected static function ok(mixed $data, int $status = 200, string $message = 'OK'): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => $message], $status);
    }

    protected static function fail(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message] + $extra, $status);
    }
}
