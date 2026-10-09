<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\LivingCourse\Connectors\ConnectorException;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Jobs\CheckSourceJob;
use Ulams\LivingCourse\Services\SourceConnections;
use Ulams\LivingCourse\Support\Presenter;

/**
 * Connecting a source through a connector, checking it on demand and rotating its webhook secret.
 *
 * @OA\Get(path="/api/admin/living-course/connectors", summary="The source connectors this installation offers, with their settings schema", tags={"Admin Living Course"}, security={{"passport": {}}}, @OA\Response(response=200, description="connectors"))
 * @OA\Post(path="/api/admin/living-course/sessions/{session}/sources/connect", summary="Add a source through a connector (Git repository, web pages, plugins); validates and fetches revision 1", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="connector", type="string"), @OA\Property(property="config", type="object"), @OA\Property(property="secrets", type="object"), @OA\Property(property="schedule", type="string"))),
 *     @OA\Response(response=201, description="connection, source and the webhook secret (shown once)"), @OA\Response(response=422, description="settings refused by the connector"))
 * @OA\Post(path="/api/admin/living-course/connections/{connection}/check", summary="Check the source now", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="connection", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=202, description="queued"), @OA\Response(response=409, description="nothing to check"))
 * @OA\Post(path="/api/admin/living-course/connections/{connection}/webhook-secret", summary="Rotate the webhook secret (returned once)", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="connection", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="the new secret"))
 */
class ConnectController extends Controller
{
    use ResolvesLivingCourse;

    public function __construct(private readonly SourceConnectorRegistry $connectors, private readonly SourceConnections $connections)
    {
    }

    public function connectors(Request $request): JsonResponse
    {
        return self::ok(array_values(array_map(fn ($c) => [
            'key' => $c->key(), 'label' => $c->label(), 'configSchema' => $c->configSchema(), 'secretFields' => $c->secretFields(), 'webhooks' => $c->supportsWebhooks(),
        ], $this->connectors->enabled())));
    }

    public function connect(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'act');
        $data = $request->validate([
            'connector' => ['required', 'string', 'max:32'],
            'config' => ['required', 'array'],
            'secrets' => ['nullable', 'array'],
            'schedule' => ['nullable', 'string'],
        ]);
        try {
            $result = $this->connections->connect($s, $data['connector'], $data['config'], (array) ($data['secrets'] ?? []), $data['schedule'] ?? null, (int) $request->user()->getKey());
        } catch (ConnectorException $e) {
            return self::fail($e->getMessage(), 422);
        }
        $c = $result['connection'];

        return self::ok([
            'connection' => Presenter::connection($c),
            'source' => Presenter::source($result['source'], $c),
            'webhookSecret' => $result['webhookSecret'],
        ], 201);
    }

    public function check(Request $request, string $connection): JsonResponse
    {
        [, $c] = $this->connectionFor($request, $connection, 'act');
        if ($c->connector === 'upload') {
            return self::fail('An uploaded source has nothing to check: upload a new version instead.', 409);
        }
        if ($c->status === 'paused') {
            return self::fail('This source is paused. Resume it before checking.', 409);
        }
        CheckSourceJob::dispatchFor($c->id, 'manual', (int) $request->user()->getKey());

        return self::ok(['queued' => true, 'connectionId' => $c->id], 202);
    }

    public function rotateSecret(Request $request, string $connection): JsonResponse
    {
        [, $c] = $this->connectionFor($request, $connection, 'act');
        if (!$this->connectors->has($c->connector) || !$this->connectors->get($c->connector)->supportsWebhooks()) {
            return self::fail('This source does not use webhooks.', 409);
        }

        return self::ok(['webhookSecret' => $this->connections->rotateWebhookSecret($c, (int) $request->user()->getKey())]);
    }
}
