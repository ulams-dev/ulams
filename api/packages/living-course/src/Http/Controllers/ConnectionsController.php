<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Services\AuditLog;
use Ulams\LivingCourse\Support\Presenter;

/**
 * Settings of a source connection and disconnecting it (history is kept).
 *
 * @OA\Put(path="/api/admin/living-course/connections/{connection}", summary="Change a connection: schedule, automatic analysis, learner notice settings, paused", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="connection", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(type="object")),
 *     @OA\Response(response=200, description="connection"), @OA\Response(response=403, description="not allowed"), @OA\Response(response=404, description="unknown"), @OA\Response(response=422, description="invalid"))
 * @OA\Delete(path="/api/admin/living-course/connections/{connection}", summary="Disconnect a source (revisions and the audit trail are kept)", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="connection", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="disconnected"), @OA\Response(response=403, description="not allowed"), @OA\Response(response=404, description="unknown"))
 */
class ConnectionsController extends Controller
{
    use ResolvesLivingCourse;

    public function __construct(private readonly AuditLog $audit)
    {
    }

    public function update(Request $request, string $connection): JsonResponse
    {
        [$session, $c] = $this->connectionFor($request, $connection, 'act');
        $data = $request->validate([
            'schedule' => ['sometimes', Rule::in(Connection::SCHEDULES)],
            'autoAnalyse' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'paused'])],
            'settings' => ['sometimes', 'array'],
            'settings.show_pending_to_learners' => ['sometimes', 'boolean'],
            'settings.notify_learners_of_updates' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['schedule']) && $c->connector === 'upload' && $data['schedule'] !== 'manual') {
            return self::fail('An uploaded source has nothing to check on a schedule; new versions appear when you upload them.', 422);
        }
        $changes = [];
        if (isset($data['schedule'])) {
            $changes['schedule'] = $data['schedule'];
            $c->schedule = $data['schedule'];
        }
        if (isset($data['autoAnalyse'])) {
            $changes['autoAnalyse'] = $data['autoAnalyse'];
            $c->auto_analyse = $data['autoAnalyse'];
        }
        if (isset($data['status'])) {
            $changes['status'] = $data['status'];
            $c->status = $data['status'];
            $c->failure_count = 0;
        }
        if (isset($data['settings'])) {
            $c->settings = array_merge((array) $c->settings, array_intersect_key($data['settings'], array_flip(['show_pending_to_learners', 'notify_learners_of_updates'])));
            $changes['settings'] = $data['settings'];
        }
        $c->save();
        $this->audit->record('connection.updated', ['session_id' => $session->id, 'subject_type' => 'connection', 'subject_id' => $c->id, 'source_id' => $c->source_id, 'data' => ['changes' => $changes]]);

        return self::ok(Presenter::connection($c->refresh()));
    }

    public function destroy(Request $request, string $connection): JsonResponse
    {
        [$session, $c] = $this->connectionFor($request, $connection, 'act');
        $c->forceFill(['status' => 'paused', 'schedule' => 'manual', 'secrets' => null, 'next_check_at' => null])->save();
        $this->audit->record('connection.disconnected', ['session_id' => $session->id, 'subject_type' => 'connection', 'subject_id' => $c->id, 'source_id' => $c->source_id, 'data' => ['connector' => $c->connector]]);

        return self::ok(Presenter::connection($c->refresh()));
    }
}
