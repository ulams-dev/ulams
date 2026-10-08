<?php

namespace EscolaLms\Payments\Gateway\Responses;

use EscolaLms\Payments\Gateway\Requests\NoneGatewayRequest;
use Omnipay\Common\Message\ResponseInterface;
use Przelewy24\Api\Responses\Transaction\TransactionRefundResponse;
use Przelewy24\Exceptions\Przelewy24Exception;

class Przelewy24RefundResponse implements ResponseInterface
{
    private bool $success;
    private ?string $request_id;
    private ?string $refunds_uuid;
    private ?string $message;

    public function __construct(bool $success = true, ?string $request_id = null, ?string $refunds_uuid = null, ?string $message = '')
    {
        $this->success = $success;
        $this->request_id = $request_id;
        $this->refunds_uuid = $refunds_uuid;
        $this->message = $message ?? __('Transaction refunded');
    }

    public static function from(string $request_id, string $refunds_uuid): self
    {
        return new self(
            true,
            $request_id,
            $refunds_uuid
        );
    }

    public static function fromApiResponseException(Przelewy24Exception $exception): self
    {
        return new self(
            false,
            null,
            null,
            $exception->getMessage(),
        );
    }

    public function getRequestId(): string
    {
        return $this->request_id;
    }

    public function getRefundsUuid(): string
    {
        return $this->refunds_uuid;
    }

    public function getData()
    {
        return [
            'request_id' => $this->request_id,
            'refunds_uuid' => $this->refunds_uuid,
            'message' => $this->message,
        ];
    }

    public function getRequest()
    {
        return new NoneGatewayRequest();
    }

    public function isSuccessful()
    {
        return $this->success;
    }

    public function isRedirect()
    {
        return false;
    }

    public function isCancelled()
    {
        return false;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getCode()
    {
        return '0';
    }

    public function getTransactionReference()
    {
        return $this->refunds_uuid;
    }
}
