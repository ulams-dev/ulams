<?php

namespace EscolaLms\Payments\Gateway\Responses;

class CallbackRefundResponse
{
    private bool $success;
    private ?int $order_id;
    private ?string $request_id;

    private ?string $refunds_uuid;

    private ?string $error;

    public function __construct(bool $success = true, ?int $order_id = null, ?string $request_id = null, ?string $refunds_uuid = null, ?string $error = null)
    {
        $this->success = $success;
        $this->order_id = $order_id;
        $this->request_id = $request_id;
        $this->refunds_uuid = $refunds_uuid;
        $this->error = $error;
    }

    public function getSuccess(): bool
    {
        return $this->success;
    }

    public function getOrderId(): ?int
    {
        return $this->order_id;
    }

    public function getRequestId(): ?string
    {
        return $this->request_id;
    }

    public function getRefundsUuid(): ?string
    {
        return $this->refunds_uuid;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
