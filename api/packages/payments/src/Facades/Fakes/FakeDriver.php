<?php

namespace Ulams\Payments\Facades\Fakes;

use Ulams\Payments\Entities\PaymentsConfig;
use Ulams\Payments\Gateway\Drivers\AbstractDriver;
use Ulams\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use Ulams\Payments\Gateway\Drivers\Przelewy24Driver;
use Ulams\Payments\Gateway\Drivers\StripeDriver;
use Ulams\Payments\Gateway\Responses\CallbackRefundResponse;
use Ulams\Payments\Gateway\Responses\CallbackResponse;
use Ulams\Payments\Gateway\Responses\NoneGatewayResponse;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\Request;
use Omnipay\Common\Message\ResponseInterface;

class FakeDriver extends AbstractDriver implements GatewayDriverContract
{
    protected ?string $requested_driver = null;

    public function __construct(PaymentsConfig $config, ?string $requested_driver = null)
    {
        parent::__construct($config);
        $this->requested_driver = $requested_driver;
    }

    public function purchase(Payment $payment, array $parameters = []): ResponseInterface
    {
        $this->throwExceptionIfMissingParameters($parameters);
        return new NoneGatewayResponse();
    }

    public function callback(Request $request, array $parameters = []): CallbackResponse
    {
        return new CallbackResponse();
    }

    public static function requiredParameters(): array
    {
        return [];
    }

    protected function getRequiredParameters(): array
    {
        switch ($this->requested_driver) {
            case 'stripe':
                return StripeDriver::requiredParameters();
            case 'przelewy24':
                return Przelewy24Driver::requiredParameters();
            default:
                return self::requiredParameters();
        }
    }

    public function getRequestedDriver(): ?string
    {
        return $this->requested_driver;
    }

    public function callbackRefund(Request $request, array $parameters = []): CallbackRefundResponse
    {
        return new CallbackRefundResponse();
    }

    public function refund(Request $request, Payment $payment, array $parameters = []): ResponseInterface
    {
        return new NoneGatewayResponse();
    }

    public function ableToRenew(): bool
    {
        return true;
    }
}
