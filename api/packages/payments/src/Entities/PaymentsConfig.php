<?php

namespace EscolaLms\Payments\Entities;

use EscolaLms\Payments\Enums\Currency;

class PaymentsConfig
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getDefaultGateway(): string
    {
        return $this->config['default_gateway'];
    }

    public function getDefaultCurrency(): Currency
    {
        if (Currency::isValidValue($this->config['default_currency'])) {
            return Currency::fromValue($this->config['default_currency']);
        }
        return Currency::USD();
    }

    public function isStripeEnabled(): bool
    {
        return $this->config['drivers']['stripe']['enabled'] ?? false;
    }

    public function getStripeSecretKey(): ?string
    {
        return $this->config['drivers']['stripe']['secret_key'] ?? null;
    }

    public function getStripePublishableKey(): ?string
    {
        return $this->config['drivers']['stripe']['publishable_key'] ?? null;
    }

    public function getStripeAllowedPaymentMethodTypes(): array
    {
        return $this->config['drivers']['stripe']['allowed_payment_method_types'] ?? ['card'];
    }

    public function hasValidConfigForStripe(): bool
    {
        return !is_null($this->getStripeSecretKey())
            && !is_null($this->getStripePublishableKey());
    }

    public function isPrzelewy24Enabled(): bool
    {
        return $this->config['drivers']['przelewy24']['enabled'] ?? false;
    }

    public function getPrzelewy24Live(): bool
    {
        return $this->config['drivers']['przelewy24']['live'] ?? true;
    }

    public function getPrzelewy24MerchantId(): ?string
    {
        return $this->config['drivers']['przelewy24']['merchant_id'] ?? null;
    }

    public function getPrzelewy24PosId(): ?string
    {
        return $this->config['drivers']['przelewy24']['pos_id'] ?? $this->getPrzelewy24MerchantId();
    }

    public function getPrzelewy24ApiKey(): ?string
    {
        return $this->config['drivers']['przelewy24']['api_key'] ?? null;
    }

    public function getPrzelewy24Crc(): ?string
    {
        return $this->config['drivers']['przelewy24']['crc'] ?? null;
    }

    public function hasValidConfigForPrzelewy24(): bool
    {
        return !is_null($this->getPrzelewy24MerchantId())
            && !is_null($this->getPrzelewy24PosId())
            && !is_null($this->getPrzelewy24ApiKey())
            && !is_null($this->getPrzelewy24Crc());
    }

    public function shouldThrowOnPaymentError(): bool
    {
        return true;
    }

    public function isRevenueCatEnabled(): bool
    {
        return $this->config['drivers']['revenuecat']['enabled'] ?? false;
    }

}
