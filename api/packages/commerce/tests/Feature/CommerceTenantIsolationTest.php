<?php

namespace Ulams\Commerce\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Ulams\Auth\Support\TokenScopes;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\Entitlements\Models\Entitlement;

/**
 * Tenants have their own database, secret and provider channel (ADR 0007, 0130). The one commerce endpoint is
 * public, so isolation rests on the per-tenant secret and on the channel binding, checked on the webhook, on the
 * re-fetched order and on the reconciliation feed.
 */
class CommerceTenantIsolationTest extends OrderFlowTestCase
{
    public function testAnEventSignedForTenantAIsRefusedByTenantB(): void
    {
        $user = $this->user();
        $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $event = $this->sylius->pay('77');

        // the request is addressed to tenant B: its own secret and channel
        config(['commerce.webhook.secret' => 'whsec_tenant_globex', 'commerce.channel' => 'globex']);

        $this->deliver($event, self::SECRET)->assertStatus(400);
        $this->assertSame(0, DB::table('commerce_order_events')->count());
    }

    public function testAnEventForTenantAWithTenantBsValidSignatureIsRefusedByTheChannelBinding(): void
    {
        $this->course();
        config(['commerce.webhook.secret' => 'whsec_tenant_globex', 'commerce.channel' => 'globex']);
        $this->sylius->channel = 'acme';
        $this->sylius->placeOrder('77', $this->user()->getKey(), ['991' => 'variant-course-1'], 'acme');
        $event = $this->sylius->pay('77');

        $this->deliver($event, 'whsec_tenant_globex')->assertStatus(400);
        $this->assertSame(0, Entitlement::query()->count());
    }

    public function testTenantBNeverReadsTenantAOrdersThroughTheRefetch(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1'], 'acme');
        $event = $this->sylius->pay('77');
        $event['channel_code'] = 'globex';
        config(['commerce.webhook.secret' => 'whsec_tenant_globex', 'commerce.channel' => 'globex']);

        $this->deliver($event, 'whsec_tenant_globex')->assertStatus(202);

        $this->assertSame(0, Entitlement::query()->count());
        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertDatabaseHas('commerce_order_events', ['order_id' => '77', 'outcome' => 'rejected']);
    }

    public function testTenantBsReconciliationNeverAppliesTenantAEvents(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1'], 'acme');
        $this->sylius->pay('77');
        config(['commerce.channel' => 'globex']);

        $this->artisan('ulams:commerce:reconcile')->assertSuccessful();

        $this->assertSame(0, DB::table('commerce_order_events')->count());
        $this->assertFalse($this->hasAccess($user, $course));
    }

    public function testTheOnlyCommerceEndpointIsPublicAndNeedsNoToken(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/commerce/'));

        $this->assertSame(['api/commerce/webhooks/{provider}'], $routes->map->uri()->values()->all());
        foreach ($routes as $route) {
            $this->assertEmpty(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_contains($m, 'auth')), 'no session or token guard: the signature is the credential');
        }
        $this->assertSame('public', TokenScopes::requirement('api/commerce/webhooks/{provider}', 'POST')['area']);
    }
}
