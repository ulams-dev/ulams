<?php

namespace Ulams\LearnerInsights\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Http\Controllers\Swagger\ClientSignalSwagger;
use Ulams\LearnerInsights\Http\Requests\ClientSignalRequest;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;

class ClientSignalController extends Controller implements ClientSignalSwagger
{
    public function store(ClientSignalRequest $request, SignalRecorder $recorder): JsonResponse
    {
        $user = $request->user();
        $topic = Topic::query()->with('lesson')->find((int) $request->integer('topicId'));
        if ($topic === null) {
            return response()->json(['message' => 'Topic not found'], 404);
        }
        if (!Gate::forUser($user)->allows('attend', $topic->lesson)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $recorder->queue(new SignalData(
            (int) $user->getKey(),
            $request->string('type')->toString(),
            'client',
            $request->string('ref')->toString(),
            CarbonImmutable::now(),
            null,
            (int) $topic->getKey(),
            null,
            $request->filled('value') ? (float) $request->input('value') : null,
            ['media' => $request->input('media')],
        ));

        return response()->json(['accepted' => true], 202);
    }
}
