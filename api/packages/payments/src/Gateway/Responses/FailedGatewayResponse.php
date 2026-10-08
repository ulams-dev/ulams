<?php

namespace Ulams\Payments\Gateway\Responses;

use Ulams\Payments\Gateway\Requests\NoneGatewayRequest;
use Omnipay\Common\Message\ResponseInterface;

/**
 * A purchase that the driver refused before or without contacting a provider.
 */
class FailedGatewayResponse implements ResponseInterface
{
    private string $message;
    private string $code;

    public function __construct(string $message, string $code = 'payment_refused')
    {
        $this->message = $message;
        $this->code = $code;
    }

    public function getData()
    {
        return null;
    }

    public function getRequest()
    {
        return new NoneGatewayRequest();
    }

    public function isSuccessful()
    {
        return false;
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
        return $this->code;
    }

    public function getTransactionReference()
    {
        return null;
    }
}
