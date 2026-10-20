<?php

namespace Ulams\Webhooks\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ulams\Core\Http\SafeHttp;
use Ulams\Core\Http\UnsafeUrlException;
use Ulams\Webhooks\Jobs\DeliverWebhookJob;
use Ulams\Webhooks\Models\WebhookDelivery;
use Ulams\Webhooks\Models\WebhookEndpoint;
use Ulams\Webhooks\Support\EventCatalog;
use Ulams\Webhooks\Support\WebhookSignature;

/**
 * Registering and changing endpoints, rotating secrets, test sends and replays. The URL is checked
 * here for a clear error message and again at every delivery (the address can change in between).
 */
class EndpointService
{
    public function __construct(private readonly WebhookDispatcher $dispatcher, private readonly DeliveryRunner $runner)
    {
    }

    /**
     * @param array{url:string,description?:?string,events:list<string>} $data
     * @return array{0:WebhookEndpoint,1:string} the endpoint and its secret, which is shown once
     */
    public function create(array $data, ?int $userId): array
    {
        if (WebhookEndpoint::query()->count() >= (int) config('ulams_webhooks.max_endpoints')) {
            throw ValidationException::withMessages(['url' => 'This academy already has the maximum number of webhook endpoints (' . config('ulams_webhooks.max_endpoints') . '). Delete one first.']);
        }
        $this->assertUrl($data['url']);
        $secret = WebhookSignature::newSecret();
        $endpoint = WebhookEndpoint::query()->create([
            'url' => $data['url'],
            'description' => $data['description'] ?? null,
            'events' => $this->events($data['events']),
            'api_version' => (string) config('ulams_webhooks.api_version'),
            'secret' => $secret,
            'secret_hint' => substr($secret, -4),
            'enabled' => true,
            'created_by' => $userId,
        ]);

        return [$endpoint, $secret];
    }

    /** @param array{url?:string,description?:?string,events?:list<string>,enabled?:bool} $data */
    public function update(WebhookEndpoint $endpoint, array $data): WebhookEndpoint
    {
        if (isset($data['url']) && $data['url'] !== $endpoint->url) {
            $this->assertUrl($data['url']);
            $endpoint->url = $data['url'];
        }
        if (array_key_exists('description', $data)) {
            $endpoint->description = $data['description'];
        }
        if (isset($data['events'])) {
            $endpoint->events = $this->events($data['events']);
        }
        if (isset($data['enabled'])) {
            $endpoint->enabled = (bool) $data['enabled'];
            if ($endpoint->enabled) {
                $endpoint->consecutive_failures = 0;
                $endpoint->disabled_reason = null;
            } else {
                $endpoint->disabled_reason = 'Disabled by an admin.';
            }
        }
        $endpoint->save();

        return $endpoint;
    }

    /** @return string the new secret, which is shown once; the old one stops working immediately */
    public function rotateSecret(WebhookEndpoint $endpoint): string
    {
        $secret = WebhookSignature::newSecret();
        $endpoint->forceFill(['secret' => $secret, 'secret_hint' => substr($secret, -4)])->save();

        return $secret;
    }

    /** Sends a sample event now, once (no retries), and returns the finished delivery. */
    public function sendTest(WebhookEndpoint $endpoint, string $type = EventCatalog::TEST_EVENT): WebhookDelivery
    {
        $eventId = 'evt_' . strtolower((string) Str::ulid());
        $delivery = $this->dispatcher->createDelivery($endpoint, $eventId, $type, EventCatalog::sample($type) + ['test' => true], true);
        $this->runner->attempt($delivery);

        return $delivery->refresh();
    }

    /** Sends the same event again to the same endpoint as a new delivery (same event id, new signature). */
    public function replay(WebhookDelivery $original): WebhookDelivery
    {
        $copy = WebhookDelivery::query()->create([
            'endpoint_id' => $original->endpoint_id,
            'event_id' => $original->event_id,
            'event_type' => $original->event_type,
            'payload' => $original->payload,
            'status' => WebhookDelivery::PENDING,
            'attempts' => 0,
            'is_test' => $original->is_test,
            'replay_of' => $original->id,
        ]);
        DeliverWebhookJob::enqueue($copy->id);

        return $copy->refresh();
    }

    public function assertUrl(string $url): void
    {
        try {
            SafeHttp::check($url, [
                'label' => 'Webhook URLs',
                'allow_insecure' => (bool) config('ulams_webhooks.allow_insecure'),
                'allowed_hosts' => (array) config('ulams_webhooks.allowed_hosts'),
            ]);
        } catch (UnsafeUrlException $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }
    }

    /**
     * @param list<string> $events
     * @return list<string>
     */
    private function events(array $events): array
    {
        $events = array_values(array_unique($events));

        return in_array(EventCatalog::WILDCARD, $events, true) ? [EventCatalog::WILDCARD] : $events;
    }
}
