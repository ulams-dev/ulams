<?php

namespace Ulams\Webhooks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Webhooks\Enums\WebhookPermissionEnum;
use Ulams\Webhooks\Models\WebhookDelivery;
use Ulams\Webhooks\Models\WebhookEndpoint;
use Ulams\Webhooks\Services\EndpointService;
use Ulams\Webhooks\Support\EventCatalog;

/**
 * Outgoing webhooks (Phase 7.3). Admin only (`webhook_manage`); the data lives in the tenant's own
 * database, so an id from another tenant never resolves.
 *
 * @OA\Schema(schema="WebhookEndpoint", type="object",
 *     @OA\Property(property="id", type="string", example="01j9z8x3v7c5qk2m4n6p8r0t1w"),
 *     @OA\Property(property="url", type="string", example="https://example.com/hooks/ulams"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="events", type="array", @OA\Items(type="string", example="course.completed"), description="Event types, or [*] for every available event"),
 *     @OA\Property(property="api_version", type="string", example="2026-10-20"),
 *     @OA\Property(property="enabled", type="boolean"),
 *     @OA\Property(property="disabled_reason", type="string", nullable=true),
 *     @OA\Property(property="consecutive_failures", type="integer"),
 *     @OA\Property(property="secret_hint", type="string", description="Last characters of the signing secret"),
 *     @OA\Property(property="secret", type="string", description="The signing secret; only in the response that creates the endpoint or rotates its secret"),
 *     @OA\Property(property="last_delivery_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"))
 * @OA\Schema(schema="WebhookDelivery", type="object",
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="endpoint_id", type="string"),
 *     @OA\Property(property="event_id", type="string", example="evt_01j9z8x3v7c5qk2m4n6p8r0t1w"),
 *     @OA\Property(property="event_type", type="string", example="course.completed"),
 *     @OA\Property(property="status", type="string", enum={"pending","retrying","succeeded","failed","cancelled"}),
 *     @OA\Property(property="attempts", type="integer"),
 *     @OA\Property(property="last_status_code", type="integer", nullable=true),
 *     @OA\Property(property="is_test", type="boolean"),
 *     @OA\Property(property="replay_of", type="string", nullable=true),
 *     @OA\Property(property="next_attempt_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="delivered_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="payload", type="object", description="The event that was signed and sent (delivery detail only)"),
 *     @OA\Property(property="attempt_log", type="array", description="One entry per HTTP attempt (delivery detail only)", @OA\Items(type="object",
 *         @OA\Property(property="number", type="integer"), @OA\Property(property="status_code", type="integer", nullable=true),
 *         @OA\Property(property="response_body", type="string", nullable=true), @OA\Property(property="error", type="string", nullable=true),
 *         @OA\Property(property="duration_ms", type="integer"), @OA\Property(property="created_at", type="string", format="date-time"))))
 * @OA\Schema(schema="WebhookEventType", type="object",
 *     @OA\Property(property="type", type="string", example="course.completed"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="status", type="string", enum={"available","planned","test"}))
 *
 * @OA\Get(path="/api/admin/webhooks/events", operationId="webhooks.events.list", summary="Webhook event types (available and planned)", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="Event types", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/WebhookEventType")))),
 *     @OA\Response(response=403, description="Needs webhook_manage"))
 * @OA\Get(path="/api/admin/webhooks/endpoints", operationId="webhooks.endpoints.list", summary="List webhook endpoints", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="Endpoints", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/WebhookEndpoint")))),
 *     @OA\Response(response=403, description="Needs webhook_manage"))
 * @OA\Post(path="/api/admin/webhooks/endpoints", operationId="webhooks.endpoints.create", summary="Create a webhook endpoint (the signing secret is returned once)", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"url","events"},
 *         @OA\Property(property="url", type="string", example="https://example.com/hooks/ulams"),
 *         @OA\Property(property="description", type="string", nullable=true),
 *         @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"course.completed"}))),
 *     @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/WebhookEndpoint"))),
 *     @OA\Response(response=403, description="Needs webhook_manage"),
 *     @OA\Response(response=422, description="Invalid events, a URL that is not https or points to a private address, or the endpoint limit"))
 * @OA\Get(path="/api/admin/webhooks/endpoints/{endpoint}", operationId="webhooks.endpoints.get", summary="One webhook endpoint", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Parameter(name="endpoint", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Endpoint", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/WebhookEndpoint"))),
 *     @OA\Response(response=404, description="Not found"))
 * @OA\Put(path="/api/admin/webhooks/endpoints/{endpoint}", operationId="webhooks.endpoints.update", summary="Change a webhook endpoint (URL, events, enabled)", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Parameter(name="endpoint", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="url", type="string"), @OA\Property(property="description", type="string", nullable=true),
 *         @OA\Property(property="events", type="array", @OA\Items(type="string")), @OA\Property(property="enabled", type="boolean"))),
 *     @OA\Response(response=200, description="Updated", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/WebhookEndpoint"))),
 *     @OA\Response(response=404, description="Not found"), @OA\Response(response=422, description="Invalid input"))
 * @OA\Delete(path="/api/admin/webhooks/endpoints/{endpoint}", operationId="webhooks.endpoints.delete", summary="Delete a webhook endpoint and its delivery log", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Parameter(name="endpoint", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Not found"))
 * @OA\Post(path="/api/admin/webhooks/endpoints/{endpoint}/rotate-secret", operationId="webhooks.endpoints.rotate-secret", summary="Replace the signing secret (returned once; the old one stops working at once)", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Parameter(name="endpoint", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Endpoint with the new secret", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/WebhookEndpoint"))),
 *     @OA\Response(response=404, description="Not found"))
 * @OA\Post(path="/api/admin/webhooks/endpoints/{endpoint}/test", operationId="webhooks.endpoints.test", summary="Send a sample event once and return the result", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Parameter(name="endpoint", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="event_type", type="string", example="course.completed", description="A sample of this event type; default webhook.test"))),
 *     @OA\Response(response=200, description="The finished delivery", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/WebhookDelivery"))),
 *     @OA\Response(response=404, description="Not found"), @OA\Response(response=429, description="Too many test sends"))
 * @OA\Get(path="/api/admin/webhooks/deliveries", operationId="webhooks.deliveries.list", summary="Delivery log (newest first)", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Parameter(name="endpoint_id", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","retrying","succeeded","failed","cancelled"})),
 *     @OA\Parameter(name="event_type", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", maximum=100)),
 *     @OA\Response(response=200, description="Deliveries and pagination meta", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/WebhookDelivery")), @OA\Property(property="meta", type="object"))))
 * @OA\Get(path="/api/admin/webhooks/deliveries/{delivery}", operationId="webhooks.deliveries.get", summary="One delivery with its payload and every attempt", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Parameter(name="delivery", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Delivery", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/WebhookDelivery"))),
 *     @OA\Response(response=404, description="Not found"))
 * @OA\Post(path="/api/admin/webhooks/deliveries/{delivery}/replay", operationId="webhooks.deliveries.replay", summary="Send a delivery again as a new delivery with the same event id", tags={"Admin Webhooks"}, security={{"passport": {}}},
 *     @OA\Parameter(name="delivery", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=202, description="The new delivery, queued", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/WebhookDelivery"))),
 *     @OA\Response(response=404, description="Not found"), @OA\Response(response=429, description="Too many replays"))
 */
class WebhooksController extends UlamsBaseController
{
    public function __construct(private readonly EndpointService $endpoints)
    {
    }

    public function events(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->sendResponse(array_values(EventCatalog::all()));
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->sendResponse(WebhookEndpoint::query()->orderBy('created_at')->orderBy('id')->get()->map(fn (WebhookEndpoint $e) => self::presentEndpoint($e))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate($this->rules(true));
        [$endpoint, $secret] = $this->endpoints->create($data, (int) $request->user()->getKey());

        return $this->sendResponse(self::presentEndpoint($endpoint, $secret), 'Webhook endpoint created. Copy the signing secret now: it is not shown again.', 201);
    }

    public function show(Request $request, string $endpoint): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->sendResponse(self::presentEndpoint($this->endpointOrFail($endpoint)));
    }

    public function update(Request $request, string $endpoint): JsonResponse
    {
        $this->authorizeManage($request);
        $model = $this->endpointOrFail($endpoint);
        $data = $request->validate($this->rules(false));

        return $this->sendResponse(self::presentEndpoint($this->endpoints->update($model, $data)), 'Webhook endpoint updated.');
    }

    public function destroy(Request $request, string $endpoint): JsonResponse
    {
        $this->authorizeManage($request);
        $this->endpointOrFail($endpoint)->delete();

        return $this->sendSuccess('Webhook endpoint deleted.');
    }

    public function rotateSecret(Request $request, string $endpoint): JsonResponse
    {
        $this->authorizeManage($request);
        $model = $this->endpointOrFail($endpoint);
        $secret = $this->endpoints->rotateSecret($model);

        return $this->sendResponse(self::presentEndpoint($model->refresh(), $secret), 'Signing secret replaced. Copy it now: it is not shown again.');
    }

    public function test(Request $request, string $endpoint): JsonResponse
    {
        $this->authorizeManage($request);
        $model = $this->endpointOrFail($endpoint);
        $data = $request->validate(['event_type' => ['nullable', 'string', Rule::in(array_keys(EventCatalog::all()))]]);
        $delivery = $this->endpoints->sendTest($model, $data['event_type'] ?? EventCatalog::TEST_EVENT);

        return $this->sendResponse(self::presentDelivery($delivery, true), $delivery->status === WebhookDelivery::SUCCEEDED ? 'The endpoint accepted the test event.' : 'The endpoint did not accept the test event.');
    }

    public function deliveries(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'endpoint_id' => ['nullable', 'string', 'max:26'],
            'status' => ['nullable', Rule::in([WebhookDelivery::PENDING, WebhookDelivery::RETRYING, WebhookDelivery::SUCCEEDED, WebhookDelivery::FAILED, WebhookDelivery::CANCELLED])],
            'event_type' => ['nullable', 'string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = WebhookDelivery::query()
            ->when($data['endpoint_id'] ?? null, fn ($q, $v) => $q->where('endpoint_id', strtolower($v)))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($data['event_type'] ?? null, fn ($q, $v) => $q->where('event_type', $v))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 25));
        $meta = $page->toArray();
        unset($meta['data']);

        return $this->sendResponseWithMeta($page->getCollection()->map(fn (WebhookDelivery $d) => self::presentDelivery($d))->all(), $meta);
    }

    public function showDelivery(Request $request, string $delivery): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->sendResponse(self::presentDelivery($this->deliveryOrFail($delivery), true));
    }

    public function replay(Request $request, string $delivery): JsonResponse
    {
        $this->authorizeManage($request);
        $copy = $this->endpoints->replay($this->deliveryOrFail($delivery));

        return $this->sendResponse(self::presentDelivery($copy), 'Delivery queued again.', 202);
    }

    private function authorizeManage(Request $request): void
    {
        if (!$request->user()?->can(WebhookPermissionEnum::WEBHOOK_MANAGE)) {
            throw new AccessDeniedHttpException('Managing webhooks needs the webhook_manage permission.');
        }
    }

    /** @return array<string,mixed> */
    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'url' => [$required, 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => [$required, 'array', 'min:1'],
            'events.*' => ['string', Rule::in(EventCatalog::subscribable())],
            'enabled' => $creating ? ['prohibited'] : ['sometimes', 'boolean'],
        ];
    }

    private function endpointOrFail(string $id): WebhookEndpoint
    {
        return WebhookEndpoint::query()->findOrFail(strtolower($id));
    }

    private function deliveryOrFail(string $id): WebhookDelivery
    {
        return WebhookDelivery::query()->findOrFail(strtolower($id));
    }

    /** @return array<string,mixed> */
    private static function presentEndpoint(WebhookEndpoint $e, ?string $secret = null): array
    {
        return [
            'id' => $e->id,
            'url' => $e->url,
            'description' => $e->description,
            'events' => $e->events,
            'api_version' => $e->api_version,
            'enabled' => $e->enabled,
            'disabled_reason' => $e->disabled_reason,
            'consecutive_failures' => $e->consecutive_failures,
            'secret_hint' => $e->secret_hint,
            'last_delivery_at' => $e->last_delivery_at?->toIso8601String(),
            'created_at' => $e->created_at?->toIso8601String(),
        ] + ($secret !== null ? ['secret' => $secret] : []);
    }

    /** @return array<string,mixed> */
    private static function presentDelivery(WebhookDelivery $d, bool $detail = false): array
    {
        $row = [
            'id' => $d->id,
            'endpoint_id' => $d->endpoint_id,
            'event_id' => $d->event_id,
            'event_type' => $d->event_type,
            'status' => $d->status,
            'attempts' => $d->attempts,
            'last_status_code' => $d->last_status_code,
            'is_test' => $d->is_test,
            'replay_of' => $d->replay_of,
            'next_attempt_at' => $d->next_attempt_at?->toIso8601String(),
            'delivered_at' => $d->delivered_at?->toIso8601String(),
            'created_at' => $d->created_at?->toIso8601String(),
        ];
        if ($detail) {
            $row['payload'] = json_decode($d->payload, true);
            $row['attempt_log'] = $d->attemptLog->map(fn ($a) => [
                'number' => $a->number,
                'status_code' => $a->status_code,
                'response_body' => $a->response_body,
                'error' => $a->error,
                'duration_ms' => $a->duration_ms,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->all();
        }

        return $row;
    }
}
