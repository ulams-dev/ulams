<?php

namespace Tests\Integrations;

use Tests\TestCase;

/**
 * These routes only make sense for a signed-in user. They used to rely on a policy inside the FormRequest,
 * so a guest got 403, or 404 for a record that does not exist, which shows which ids exist. `auth:api` on the
 * route answers 401 first, whatever the id (ADR 0190; the full walk is TenantIsolationCoverageTest).
 */
class UserBoundRoutesRequireAuthTest extends TestCase
{
    public function testGuestsGet401NotARecordDependentAnswer(): void
    {
        $routes = [
            ['GET', '/api/notifications'],
            ['GET', '/api/notifications/events'],
            ['POST', '/api/notifications/1/read'],
            ['POST', '/api/notifications/read-all'],
            ['GET', '/api/payments'],
            ['GET', '/api/payments/1'],
            ['GET', '/api/pdfs'],
            ['GET', '/api/pdfs/1'],
            ['GET', '/api/pdfs/generate/1'],
            ['GET', '/api/order-invoices/1'],
            ['POST', '/api/cart/voucher'],
            ['DELETE', '/api/cart/voucher'],
            ['GET', '/api/questionnaire/course/1/1'],
            ['POST', '/api/questionnaire/course/1/1'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }
    }

    public function testRoutesThatStayPublicAreNotBehindAuth(): void
    {
        $this->assertNotSame(401, $this->json('GET', '/api/pdfs/fonts')->getStatusCode());
        $this->assertNotSame(401, $this->json('GET', '/api/questionnaire/stars/course/1')->getStatusCode());
        $this->json('GET', '/api/payments-gateways')->assertOk();
    }
}
