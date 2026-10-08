<?php

namespace EscolaLms\Payments\Tests\Api;

use EscolaLms\Payments\Facades\Payments;
use EscolaLms\Payments\Gateway\Drivers\Przelewy24Driver;
use EscolaLms\Payments\Gateway\Drivers\RevenueCatDriver;
use EscolaLms\Payments\Gateway\Drivers\StripeDriver;
use EscolaLms\Payments\Tests\TestCase;
use EscolaLms\Payments\Tests\Traits\CreatesBillable;

class PaymentGatewayTest extends TestCase
{
    use CreatesBillable;

    public function testStudentCanListGatewaysWithRequiredParams(): void
    {
        $billable = $this->createBillableStudent();

        $response = $this->actingAs($billable)->json('GET', 'api/payments-gateways/', [
            'per_page' => 20
        ]);
        $response->assertOk();
        $response->assertJsonFragment([
            'default_gateway' => Payments::getPaymentsConfig()->getDefaultGateway(),
            'gateways' => [
                'stripe' => [
                    'enabled' => Payments::getPaymentsConfig()->isStripeEnabled(),
                    'parameters' => StripeDriver::requiredParameters()
                ],
                'przelewy24' => [
                    'enabled' => Payments::getPaymentsConfig()->isPrzelewy24Enabled(),
                    'parameters' => Przelewy24Driver::requiredParameters()
                ],
                'revenuecat' => [
                    'enabled' => Payments::getPaymentsConfig()->isRevenueCatEnabled(),
                    'parameters' => RevenueCatDriver::requiredParameters()
                ]
            ]
        ]);
    }
}
