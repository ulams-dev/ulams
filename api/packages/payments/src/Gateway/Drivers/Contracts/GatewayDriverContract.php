<?php

namespace Ulams\Payments\Gateway\Drivers\Contracts;

use Ulams\Payments\Gateway\Responses\CallbackRefundResponse;
use Ulams\Payments\Gateway\Responses\CallbackResponse;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\Request;
use Omnipay\Common\Message\ResponseInterface;

interface GatewayDriverContract
{
    public function purchase(Payment $payment, array $parameters = []): ResponseInterface;
    public function callback(Request $request, array $parameters = []): CallbackResponse;
    public function callbackRefund(Request $request, array $parameters = []): CallbackRefundResponse;

    public function refund(Request $request, Payment $payment, array $parameters = []): ResponseInterface;

    public static function requiredParameters(): array;

    public function throwExceptionForResponse(ResponseInterface $response): void;
    public function throwExceptionIfMissingParameters(array $parameters): void;

    public function ableToRenew(): bool;
}
