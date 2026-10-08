<?php

namespace EscolaLms\Payments\Facades\Fakes;

use EscolaLms\Payments\Gateway\GatewayManager;
use EscolaLms\Payments\Facades\Fakes\FakeDriver;

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
