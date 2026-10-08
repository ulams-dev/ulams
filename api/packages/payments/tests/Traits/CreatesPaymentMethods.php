<?php

namespace EscolaLms\Payments\Tests\Traits;

use EscolaLms\Payments\Facades\Payments;
use Illuminate\Support\Carbon;
use Omnipay\Common\CreditCard;
use Omnipay\Omnipay;

trait CreatesPaymentMethods
{
    protected function getGateway()
    {
        $this->gateway = Omnipay::create('Stripe\PaymentIntents');
        $this->gateway->setApiKey(Payments::getPaymentsConfig()->getStripeSecretKey());
        return $this->gateway;
    }

    protected function getPaymentMethodId(string $creditCardNumber = '4242424242424242'): string
    {
        $card = $this->getGateway()->createCard([
            'card' => new CreditCard([
                'number' => $creditCardNumber,
                'expiryMonth' => 12,
                'expiryYear' => Carbon::now()->addYear()->format('Y'),
                'cvv' => 123,
            ])
        ])->send()->getData();

        return $card['id'];
    }
}
