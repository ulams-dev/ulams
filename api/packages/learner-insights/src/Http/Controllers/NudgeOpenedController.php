<?php

namespace Ulams\LearnerInsights\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\LearnerInsights\Models\Intervention;
use Ulams\LearnerInsights\Services\NudgeService;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;

class NudgeOpenedController extends Controller
{
    /**
     * @OA\Post(
     *   path="/api/learner-insights/nudges/opened", operationId="learner-insights.nudges.opened.post",
     *   summary="The learner followed the link of a nudge (the frontend sends the signed `n` of the link)",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"n"}, @OA\Property(property="n", type="string"))),
     *   @OA\Response(response=202, description="Recorded (once per nudge)", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=404, description="The token is not genuine or the nudge belongs to another learner")
     * )
     */
    public function store(Request $request, NudgeService $nudges, SignalRecorder $recorder): JsonResponse
    {
        $token = (string) $request->validate(['n' => ['required', 'string', 'max:80']])['n'];
        $id = $nudges->verify($token);
        $nudge = $id ? Intervention::query()->where('kind', Intervention::NUDGE)->find($id) : null;
        if ($nudge === null || (int) $nudge->user_id !== (int) $request->user()->getKey()) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $recorder->queue(new SignalData((int) $nudge->user_id, SignalType::NUDGE_OPENED, 'nudge', 'nudge_opened:' . $nudge->id, CarbonImmutable::now(), (int) $nudge->course_id, null, null, null, ['nudge_id' => $nudge->id, 'channel' => 'email']));

        return response()->json(['accepted' => true], 202);
    }
}
