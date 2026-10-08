<?php

namespace EscolaLms\Payments\Gateway\Drivers;

use EscolaLms\Payments\Entities\PaymentsConfig;
use EscolaLms\Payments\Exceptions\ParameterMissingException;
use EscolaLms\Payments\Exceptions\PaymentException;
use EscolaLms\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use Illuminate\Support\Arr;
use Omnipay\Common\Message\ResponseInterface;

abstract class AbstractDriver implements GatewayDriverContract
{
    protected PaymentsConfig $config;

    public function __construct(PaymentsConfig $config)
    {
        $this->config = $config;
    }

    public function throwExceptionForResponse(ResponseInterface $response): void
    {
        if (!$response->isSuccessful()) {
            throw new PaymentException('[' . $response->getCode() . '] '  . $response->getMessage());
        }
    }

    public function throwExceptionIfMissingParameters(array $parameters): void
    {
        if (!$this->hasAllRequiredParameters($parameters)) {
            throw new ParameterMissingException($this->missingParameters($parameters));
        }
    }

    protected function getRequiredParameters(): array
    {
        return static::requiredParameters();
    }

    public function hasAllRequiredParameters(array $parameters = []): bool
    {
        return empty($this->getRequiredParameters()) || Arr::has($parameters, $this->getRequiredParameters());
    }

    public function missingParameters(array $parameters = []): array
    {
        return array_filter($this->getRequiredParameters(), fn (string $required) => !array_key_exists($required, $parameters));
    }

    public function ableToRenew(): bool
    {
        return false;
    }
}
