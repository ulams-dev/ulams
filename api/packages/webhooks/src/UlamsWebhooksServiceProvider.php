<?php

namespace Ulams\Webhooks;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Webhooks\Console\PruneCommand;
use Ulams\Webhooks\Console\RetryDueCommand;
use Ulams\Webhooks\Services\DeliveryRunner;
use Ulams\Webhooks\Services\EndpointService;
use Ulams\Webhooks\Services\WebhookDispatcher;
use Ulams\Webhooks\Support\EventCatalog;

/**
 * Outgoing, signed webhooks (Phase 7.3): a tenant registers URLs and event types; domain events that
 * already exist are delivered as versioned JSON with an HMAC-SHA256 signature, retried with exponential
 * backoff through the queue, logged per attempt, and can be tested and replayed. SWAGGER_VERSION
 */
class UlamsWebhooksServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_webhooks';

    public $singletons = [
        WebhookDispatcher::class => WebhookDispatcher::class,
        DeliveryRunner::class => DeliveryRunner::class,
        EndpointService::class => EndpointService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsAuthServiceProvider::class);
    }

    public function boot(): void
    {
        RateLimiter::for('ulams-webhooks', fn (Request $request) => Limit::perMinute((int) config('ulams_webhooks.api_rate_limit'))->by('webhooks:' . ($request->user()?->getKey() ?? $request->ip())));
        RateLimiter::for('ulams-webhooks-send', fn (Request $request) => Limit::perMinute((int) config('ulams_webhooks.send_rate_limit'))->by('webhooks-send:' . ($request->user()?->getKey() ?? $request->ip())));

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // A listener never breaks the domain flow that fired the event: failures are reported and the event is skipped.
        foreach (EventCatalog::listened() as $class => $type) {
            Event::listen($class, function (object $event) use ($type): void {
                try {
                    $payload = EventCatalog::payloadFor($type, $event);
                    if ($payload !== null) {
                        $this->app->make(WebhookDispatcher::class)->dispatch($type, $payload['object'], $payload['key']);
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class, RetryDueCommand::class]);
            $this->publishes([__DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php')], self::CONFIG_KEY . '.config');
        }

        // per tenant: `schedule:run` runs for the platform and for every tenant domain
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('webhooks:retry-due')->everyMinute()->withoutOverlapping();
            $schedule->command('webhooks:prune')->daily();
        });
    }
}
