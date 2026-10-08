<?php

namespace EscolaLms\Payments\Gateway\Requests;

use EscolaLms\Payments\Gateway\Responses\NoneGatewayResponse;
use Omnipay\Common\Message\RequestInterface;

class NoneGatewayRequest implements RequestInterface
{
    public function initialize(array $parameters = [])
    {
    }

    public function getParameters()
    {
        return [];
    }

    public function getResponse()
    {
        return new NoneGatewayResponse();
    }

    public function getData()
    {
        return null;
    }

    public function sendData($data)
    {
        return new NoneGatewayResponse();
    }

    public function send()
    {
        return new NoneGatewayResponse();
    }
}
