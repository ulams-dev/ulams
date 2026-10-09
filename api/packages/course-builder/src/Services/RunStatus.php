<?php

namespace Ulams\CourseBuilder\Services;

use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Step;

/**
 * The public, stable shape of a run for pollers (CLI `--wait`, MCP, CI):
 * `GET /api/admin/course-builder/runs/{run}`. Internal statuses are folded into
 * queued | running | succeeded | failed | cancelled; `needsAttention` is true while the run waits
 * for the author (an answer or an approval), so a poller can stop and ask instead of looping.
 *
 * @OA\Schema(
 *     schema="CourseBuilderRunStatus",
 *     required={"id","sessionId","kind","status","steps"},
 *     @OA\Property(property="id", type="string", example="01j9z3k8m2x4q7r5t6v8w0y1ab"),
 *     @OA\Property(property="sessionId", type="string"),
 *     @OA\Property(property="kind", type="string", enum={"ingest","interview","outline","generate","patch","apply","action"}),
 *     @OA\Property(property="status", type="string", enum={"queued","running","succeeded","failed","cancelled"}),
 *     @OA\Property(property="stage", type="string", nullable=true),
 *     @OA\Property(property="needsAttention", type="boolean"),
 *     @OA\Property(property="steps", type="array", @OA\Items(
 *         @OA\Property(property="id", type="string"),
 *         @OA\Property(property="name", type="string", example="lesson:el_8f2c"),
 *         @OA\Property(property="status", type="string", enum={"queued","running","succeeded","failed"}),
 *         @OA\Property(property="error", type="string", nullable=true))),
 *     @OA\Property(property="startedAt", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="finishedAt", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="error", type="string", nullable=true)
 * )
 */
final class RunStatus
{
    private const RUN = ['queued' => 'queued', 'running' => 'running', 'needs_attention' => 'running', 'finished' => 'succeeded', 'failed' => 'failed', 'cancelled' => 'cancelled'];

    private const STEP = ['pending' => 'queued', 'queued' => 'queued', 'running' => 'running', 'done' => 'succeeded', 'failed' => 'failed'];

    /** @return array<string,mixed> */
    public static function from(Run $run): array
    {
        return [
            'id' => $run->id,
            'sessionId' => $run->session_id,
            'kind' => $run->kind,
            'status' => self::RUN[$run->status] ?? 'running',
            'stage' => $run->stage,
            'needsAttention' => $run->status === 'needs_attention',
            'steps' => $run->steps->map(fn (Step $s) => [
                'id' => $s->id,
                'name' => $s->key,
                'status' => self::STEP[$s->status] ?? 'queued',
                'error' => $s->error,
            ])->values()->all(),
            'startedAt' => $run->started_at?->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
            'error' => $run->error,
        ];
    }
}
