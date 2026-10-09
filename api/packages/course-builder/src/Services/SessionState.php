<?php

namespace Ulams\CourseBuilder\Services;

use Ulams\Ai\Contracts\LlmClient;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Pipeline\BriefService;
use Ulams\CourseBuilder\Pipeline\Llm;

/**
 * The AG-UI shared state of a session (STATE_SNAPSHOT on connect, STATE_DELTA after changes).
 */
final class SessionState
{
    public static function snapshot(Session $session): array
    {
        $client = app(LlmClient::class);

        return [
            'session' => self::summary($session),
            'brief' => $session->brief,
            'briefRows' => $session->brief ? BriefService::rows($session->brief) : [],
            'cost' => Llm::cost($session),
            'sources' => self::sources($session),
            'aiEnabled' => $client->enabled(),
            'profiles' => ['default' => $client->profileLabel('outline'), 'light' => $client->profileLabel('interview')],
            'budgetReached' => (bool) $session->stateValue('budgetReached', false),
            'activeRunId' => Run::query()->where('session_id', $session->id)->whereIn('status', ['queued', 'running'])->latest('created_at')->value('id'),
            'canUndo' => app(VersionService::class)->undoTarget($session) !== null,
            'canRedo' => (array) $session->stateValue('redo', []) !== [],
            'links' => self::links($session),
        ];
    }

    public static function summary(Session $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status,
            'courseId' => $session->course_id,
            'currentVersionId' => $session->current_version_id,
            'appliedVersionId' => $session->applied_version_id,
            'createdAt' => $session->created_at?->toIso8601String(),
            'updatedAt' => $session->updated_at?->toIso8601String(),
        ];
    }

    public static function sources(Session $session): array
    {
        return $session->sources()->get()->map(fn (Source $s) => [
            'id' => $s->id,
            'name' => $s->original_name,
            'status' => $s->status,
            'kind' => $s->kind(),
            'size' => $s->size,
            'tokens' => $s->token_estimate,
            'fragments' => (int) ($s->metadata['fragments'] ?? 0),
            'pages' => $s->metadata['pages'] ?? null,
            'title' => $s->metadata['title'] ?? null,
            'error' => $s->error,
        ])->all();
    }

    /** Admin and learner links of the applied course (relative paths; the front adds hosts). */
    public static function links(Session $session): array
    {
        if ($session->course_id === null) {
            return [];
        }
        $admin = rtrim((string) config('course_builder.admin_url'), '/');
        $front = rtrim((string) config('course_builder.front_url'), '/');

        return [
            'adminPath' => "/courses/list/{$session->course_id}",
            'learnerPath' => "/courses/{$session->course_id}",
            'landingSlug' => "course-{$session->course_id}",
            'admin' => $admin !== '' ? "{$admin}/#/courses/list/{$session->course_id}" : null,
            'learner' => $front !== '' ? "{$front}/courses/{$session->course_id}" : null,
        ];
    }
}
