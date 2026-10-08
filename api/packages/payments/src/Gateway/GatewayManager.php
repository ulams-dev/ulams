<?php

namespace EscolaLms\Payments\Gateway;

use EscolaLms\Payments\Entities\PaymentsConfig;
use EscolaLms\Payments\Exceptions\GatewayConfigException;
use EscolaLms\Payments\Gateway\Drivers\FreeDriver;
use EscolaLms\Payments\Gateway\Drivers\Przelewy24Driver;
use EscolaLms\Payments\Gateway\Drivers\RevenueCatDriver;
use EscolaLms\Payments\Gateway\Drivers\StripeDriver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Manager;

class GatewayManager extends Manager
{
    protected PaymentsConfig $paymentsConfig;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->paymentsConfig = new PaymentsConfig($this->config['escolalms_payments']);
    }

    public function getPaymentsConfig(): PaymentsConfig
    {
        return $this->paymentsConfig;
    }

    public function getDefaultDriver(): string
    {
        return $this->paymentsConfig->getDefaultGateway();
    }

    public function createFreeDriver(): FreeDriver
    {
        return new FreeDriver($this->paymentsConfig);
    }

    public function createStripeDriver(): StripeDriver
    {
        if (!$this->paymentsConfig->isStripeEnabled()) {
            throw new GatewayConfigException(__('Stripe payments gateway is disabled'));
        }
        if (!$this->paymentsConfig->hasValidConfigForStripe()) {
            throw new GatewayConfigException(__('Missing Stripe configuration'));
        }
        return new StripeDriver($this->paymentsConfig);
    }

    public function createPrzelewy24Driver(): Przelewy24Driver
    {
        if (!$this->paymentsConfig->isPrzelewy24Enabled()) {
            throw new GatewayConfigException(__('Przelewy24 payments gateway is disabled'));
        }
        if (!$this->paymentsConfig->hasValidConfigForPrzelewy24()) {
            throw new GatewayConfigException(__('Missing Przelewy24 configuration'));
        }
        return new Przelewy24Driver($this->paymentsConfig);
    }

    public function createRevenueCatDriver(): RevenueCatDriver
    {
        if (!$this->paymentsConfig->isRevenueCatEnabled()) {
            throw new GatewayConfigException(__('RevenueCat payments gateway is disabled'));
        }
        return new RevenueCatDriver($this->paymentsConfig);
    }
}
