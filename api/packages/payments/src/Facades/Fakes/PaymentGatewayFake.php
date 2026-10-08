<?php

namespace Ulams\Payments\Facades\Fakes;

use Ulams\Payments\Gateway\GatewayManager;
use Ulams\Payments\Facades\Fakes\FakeDriver;

class PaymentGatewayFake extends GatewayManager
{
    protected ?string $requested_driver = null;

    public function driver($driver = null)
    {
        $this->requested_driver = $driver;
        return new FakeDriver($this->paymentsConfig, $driver);
    }

    public function getRequestedDriver(): ?string
    {
        return $this->requested_driver;
    }
}
