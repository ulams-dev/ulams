<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ulams\LivingCourse\Connectors\InvalidSignature;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Jobs\CheckSourceJob;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Services\AuditLog;
use Ulams\LivingCourse\Services\CheckLimiter;

/**
 * Inbound webhooks of source hosts (ADR 0032). Public route, authenticated by the signature with the
 * connection's own secret: invalid signatures are refused and logged, duplicates answer 200 without
 * work, only pushes to the tracked branch that touch the tracked paths queue a (debounced) check.
 * Payloads are never sent to the model and never stored, only their digest.
 *
 * @OA\Post(path="/api/living-course/webhooks/{webhookId}", summary="Receive a push webhook of a connected repository", tags={"Living Course webhooks"},
 *     @OA\Parameter(name="webhookId", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=202, description="a check is queued"), @OA\Response(response=200, description="ignored or duplicate"),
 *     @OA\Response(response=401, description="invalid signature"), @OA\Response(response=404, description="unknown webhook"), @OA\Response(response=413, description="body too large"), @OA\Response(response=429, description="rate limit"))
 */
class WebhookController extends Controller
{
    private const MAX_BODY = 1048576;

    public function __construct(private readonly SourceConnectorRegistry $connectors, private readonly AuditLog $audit, private readonly CheckLimiter $limiter)
    {
    }

    public function receive(Request $request, string $webhookId): JsonResponse
    {
        $connection = preg_match('/^[a-z0-9]{26}$/', $webhookId) ? Connection::query()->where('webhook_id', $webhookId)->first() : null;
        if ($connection === null || !$this->connectors->has($connection->connector) || !$this->connectors->get($connection->connector)->supportsWebhooks()) {
            usleep(150000); // unknown ids answer after the same delay as known ones: nothing to learn from timing
            return response()->json(['success' => false, 'message' => 'Unknown webhook.'], 404);
        }
        $body = $request->getContent();
        if (strlen($body) > self::MAX_BODY) {
            return response()->json(['success' => false, 'message' => 'The payload is too large.'], 413);
        }
        $digest = hash('sha256', $body);
        try {
            $delivery = $this->connectors->get($connection->connector)->verifyWebhook($connection, $request);
        } catch (InvalidSignature) {
            $this->log($connection, 'invalid-' . $digest, null, false, 'rejected', $digest);
            // one audit entry per connection and minute: a flood of forged deliveries must not flood the trail
            if (Cache::add("living_course:webhook_rejected:{$connection->id}", 1, now()->addMinute())) {
                $this->audit->record('webhook.rejected', [
                    'session_id' => $connection->session_id, 'subject_type' => 'connection', 'subject_id' => $connection->id, 'source_id' => $connection->source_id,
                    'actor_type' => 'system', 'ip' => $request->ip(), 'data' => ['reason' => 'invalid signature'],
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 401);
        }
        if ($delivery === null) {
            return response()->json(['success' => true, 'data' => ['ignored' => true]], 200);
        }
        if (DB::table('living_course_webhook_deliveries')->where('connection_id', $connection->id)->where('delivery_id', $delivery->id)->exists()) {
            return response()->json(['success' => true, 'data' => ['duplicate' => true]], 200);
        }
        if ($connection->status === 'paused' || !$this->limiter->allow($connection)) {
            $this->log($connection, $delivery->id, $delivery->event, true, 'dropped', $digest);
            Log::info('living-course: webhook dropped', ['connection' => $connection->id, 'status' => $connection->status]);

            return response()->json(['success' => true, 'data' => ['dropped' => true]], 200);
        }
        $this->log($connection, $delivery->id, $delivery->event, true, 'queued', $digest);
        CheckSourceJob::dispatchFor($connection->id, 'webhook', null, (int) config('living_course.webhook_debounce_seconds', 600));

        return response()->json(['success' => true, 'data' => ['queued' => true]], 202);
    }

    private function log(Connection $connection, string $deliveryId, ?string $event, bool $valid, string $outcome, string $digest): void
    {
        DB::table('living_course_webhook_deliveries')->insertOrIgnore([
            'connection_id' => $connection->id, 'delivery_id' => mb_substr($deliveryId, 0, 100), 'event' => $event, 'signature_valid' => $valid,
            'outcome' => $outcome, 'payload_sha256' => $digest, 'received_at' => now(),
        ]);
    }
}
