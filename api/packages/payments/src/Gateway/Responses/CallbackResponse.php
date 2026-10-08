<?php

namespace EscolaLms\Payments\Gateway\Responses;

class CallbackResponse
{
    private bool $success;
    private ?string $gateway_order_id;
    private ?string $error;

    public function __construct(bool $success = true, ?string $gateway_order_id = null, ?string $error = null)
    {
        $this->success = $success;
        $this->gateway_order_id = $gateway_order_id;
        $this->error = $error;
    }

    public function getSuccess(): bool
    {
        return $this->success;
    }

    public function getGatewayOrderId(): ?string
    {
        return $this->gateway_order_id;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
