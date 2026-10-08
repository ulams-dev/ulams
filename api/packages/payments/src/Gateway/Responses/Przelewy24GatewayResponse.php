<?php

namespace EscolaLms\Payments\Gateway\Responses;

use EscolaLms\Payments\Gateway\Requests\NoneGatewayRequest;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\ResponseInterface;
use Przelewy24\Api\Responses\Transaction\RegisterTransactionResponse;
use Przelewy24\Exceptions\Przelewy24Exception;

class Przelewy24GatewayResponse implements ResponseInterface, RedirectResponseInterface
{
    private ?string $token;
    private ?string $redirectUrl;
    private string $message = '';

    protected function __construct(?string $token = null, ?string $redirectUrl = null, string $message = '')
    {
        $this->token = $token;
        $this->redirectUrl = $redirectUrl;
        $this->message = $message;
    }

    public static function fromRegisterTransactionResponse(RegisterTransactionResponse $response): self
    {
        return new self(
            $response->token(),
            $response->gatewayUrl(),
            __('Transaction registered'),
        );
    }

    public static function fromApiResponseException(Przelewy24Exception $exception): self
    {
        return new self(
            null,
            null,
            $exception->getMessage(),
        );
    }

    public function getData()
    {
        return [
            'token' => $this->token,
            'redirectUrl' => $this->redirectUrl,
            'message' => $this->message,
        ];
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
        return !empty($this->redirectUrl);
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
        return $this->token;
    }

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectData()
    {
        return ['token' => $this->token];
    }

    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    public function redirect()
    {
        // do nothing
    }
}
