<?php

namespace Ulams\Payments\Gateway\Responses;

/**
 * Result of a gateway callback.
 *
 * A callback is reachable without authentication, so a response is only successful when the driver
 * explicitly verified the payment with the provider. The default is "not successful".
 *
 * A "rejected" response means the callback could not be verified (missing or invalid signature, data that
 * does not match the payment, a status that is not final yet). Rejected callbacks must not change the payment.
 */
class CallbackResponse
{
    private bool $success;
    private ?string $gateway_order_id;
    private ?string $error;
    private bool $rejected;

    public function __construct(bool $success = false, ?string $gateway_order_id = null, ?string $error = null, bool $rejected = false)
    {
        $this->success = $success;
        $this->gateway_order_id = $gateway_order_id;
        $this->error = $error;
        $this->rejected = $rejected;
    }

    public static function success(?string $gateway_order_id = null): self
    {
        return new self(true, $gateway_order_id);
    }

    public static function failed(string $error, ?string $gateway_order_id = null): self
    {
        return new self(false, $gateway_order_id, $error);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, null, $reason, true);
    }

    public function getSuccess(): bool
    {
        return $this->success;
    }

    public function isRejected(): bool
    {
        return $this->rejected;
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
