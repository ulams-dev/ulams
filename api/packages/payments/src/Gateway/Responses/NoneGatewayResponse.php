<?php

namespace EscolaLms\Payments\Gateway\Responses;

use EscolaLms\Payments\Gateway\Requests\NoneGatewayRequest;
use Omnipay\Common\Message\ResponseInterface;

class NoneGatewayResponse implements ResponseInterface
{
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
        return true;
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
        return '';
    }

    public function getCode()
    {
        return '0';
    }

    public function getTransactionReference()
    {
        return null;
    }
}
