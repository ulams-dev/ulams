<?php

namespace Ulams\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Contracts\FetchesOrders;
use Ulams\Commerce\Jobs\ApplyOrderEvent;
use Ulams\Commerce\Services\OrderEventIntake;
use Ulams\Commerce\Support\OrderEventEnvelope;
use Ulams\Webhooks\Support\WebhookSignature;

/**
 * Inbound order events of a commerce provider (ADR 0130). Public route, authenticated by the signature with the
 * tenant's own secret, never by a session: the body is verified (HMAC over `<t>.<raw body>`, 300 s tolerance,
 * the secret being rotated out also accepted), its event id must equal the `Ulams-Event-Id` header, and its channel
 * must be this tenant's channel. A valid event is stored once per event id and queued; a duplicate answers 200
 * with no work. The payload is untrusted: only the envelope is read, and the job re-reads the order from the provider.
 *
 * @OA\Post(path="/api/commerce/webhooks/{provider}", operationId="commerce.webhooks.by-provider.post", summary="Receive a signed order event of the commerce provider", tags={"Commerce webhooks"},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=202, description="the event is stored and queued", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=200, description="duplicate delivery, nothing to do", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=400, description="invalid or expired signature, malformed event or another tenant's channel"), @OA\Response(response=404, description="unknown provider or webhooks not configured"), @OA\Response(response=413, description="body too large"), @OA\Response(response=429, description="rate limit"))
 */
class OrderWebhookController extends Controller
{
    public const EVENT_ID_HEADER = 'Ulams-Event-Id';

    public function __construct(private readonly CommerceManager $commerce, private readonly OrderEventIntake $intake)
    {
    }

    public function receive(Request $request, string $provider): JsonResponse
    {
        $secrets = array_values(array_filter([config('commerce.webhook.secret'), config('commerce.webhook.previous_secret')], fn ($s) => is_string($s) && $s !== ''));
        $channel = (string) config('commerce.channel');
        if (!$this->commerce->has($provider) || !$this->commerce->provider($provider) instanceof FetchesOrders || $secrets === [] || $channel === '') {
            return $this->refuse(404, 'Unknown provider.');
        }

        $body = $request->getContent();
        if (strlen($body) > (int) config('commerce.webhook.max_body')) {
            return $this->refuse(413, 'The payload is too large.');
        }
        if (!$this->signed($request, $body, $secrets)) {
            return $this->reject($request, $provider, 'invalid or expired signature');
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return $this->reject($request, $provider, 'body is not a JSON object');
        }
        try {
            $event = OrderEventEnvelope::fromPayload($payload);
        } catch (InvalidArgumentException $e) {
            return $this->reject($request, $provider, $e->getMessage());
        }
        if ($request->header(self::EVENT_ID_HEADER) !== $event->eventId) {
            return $this->reject($request, $provider, 'event id header does not match the payload');
        }
        if ($event->channel !== $channel) {
            return $this->reject($request, $provider, 'event is for another channel');
        }

        $record = $this->intake->record($provider, $event, OrderEventIntake::SOURCE_WEBHOOK, hash('sha256', $body));
        if ($record === null) {
            return response()->json(['success' => true, 'data' => ['duplicate' => true]], 200);
        }
        ApplyOrderEvent::dispatch($record->id)->afterCommit();

        return response()->json(['success' => true, 'data' => ['queued' => true]], 202);
    }

    /** @param string[] $secrets */
    private function signed(Request $request, string $body, array $secrets): bool
    {
        $header = (string) $request->header(WebhookSignature::HEADER);
        $tolerance = (int) config('commerce.webhook.tolerance');
        foreach ($secrets as $secret) {
            if (WebhookSignature::verify($header, $body, $secret, $tolerance)) {
                return true;
            }
        }

        return false;
    }

    private function reject(Request $request, string $provider, string $reason): JsonResponse
    {
        // no state change; the log carries the source address, never the payload
        Log::warning('commerce: webhook refused', ['provider' => $provider, 'reason' => $reason, 'ip' => $request->ip()]);

        return $this->refuse(400, 'The event was refused.');
    }

    private function refuse(int $status, string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
