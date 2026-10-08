<?php

namespace Ulams\Payments\Entities;

use Ulams\Core\Models\User;
use Ulams\Payments\Enums\Currency;
use Ulams\Payments\Enums\PaymentStatus;
use Ulams\Payments\Events\PaymentCancelled;
use Ulams\Payments\Events\PaymentFailed;
use Ulams\Payments\Events\PaymentSuccess;
use Ulams\Payments\Facades\PaymentGateway;
use Ulams\Payments\Facades\Payments;
use Ulams\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Omnipay\Common\Message\RedirectResponseInterface;
use Ramsey\Uuid\Nonstandard\Uuid;

class PaymentProcessor
{
    private Payment $payment;
    private bool $callbackRejected = false;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function setAmount(int $amount): self
    {
        $this->payment->amount = $amount;
        return $this;
    }

    public function setCurrency(?Currency $currency): self
    {
        $this->payment->currency = $currency ?? PaymentGateway::getPaymentsConfig()->getDefaultCurrency();
        return $this;
    }

    public function setUser(User $user): self
    {
        $this->payment->user()->associate($user);
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->payment->description = $description;
        return $this;
    }

    public function setOrderId(?string $orderId): self
    {
        $this->payment->order_id = $orderId;
        return $this;
    }

    public function setGatewayOrderId(?string $gatewayOrderId): self
    {
        $this->payment->gateway_order_id = $gatewayOrderId;
        return $this;
    }

    public function setPaymentDriverName(?string $driver): self
    {
        $this->payment->driver = $driver ?? PaymentGateway::getDefaultDriver();
        return $this;
    }

    public function getPaymentDriverName(): string
    {
        if ($this->payment->amount === 0) {
            return 'free';
        }
        return $this->payment->driver ?? PaymentGateway::getDefaultDriver();
    }

    public function savePayment(): self
    {
        $this->payment->save();
        return $this;
    }

    public function updatePayment(array $parameters = []): self
    {
        $this->payment->update($parameters);
        return $this;
    }

    public function purchase(array $parameters = []): self
    {
        $driver = $parameters['gateway'] ?? null;

        if (!is_null($driver) && Payments::isDriverEnabled($driver)) {
            $this->setPaymentDriverName($driver);
        } else {
            $this->setPaymentDriverName($this->getPaymentDriverName());
        }

        $currency = $parameters['currency'] ?? null;

        if (!is_null($currency) && Currency::hasValue($currency)) {
            $this->setCurrency(Currency::fromValue($currency));
        }

        $this->setRefund($parameters);
        $this->savePayment();

        $response = $this->getPaymentDriver()->purchase($this->payment, $parameters);

        if ($response->isSuccessful()) {
            $this->setSuccessful();
        } elseif ($response->isRedirect()) {
            assert($response instanceof RedirectResponseInterface);
            $this->setRedirect($response->getRedirectUrl());
        } elseif ($response->isCancelled()) {
            $this->setCancelled();
        } else {
            $this->setError($response->getMessage(), $response->getCode());
            if (PaymentGateway::getPaymentsConfig()->shouldThrowOnPaymentError()) {
                $this->getPaymentDriver()->throwExceptionForResponse($response);
            }
        }

        return $this;
    }

    /**
     * Handles a gateway callback. Callback routes are public, so:
     * - only a response the driver verified with the provider can change the payment ("rejected" ones are logged
     *   and ignored);
     * - a payment that is already settled is left as it is, so repeated notifications do not fire events twice.
     */
    public function callback(Request $request): self
    {
        $this->callbackRejected = false;

        DB::transaction(function () use ($request) {
            $this->lockPayment();

            if ($this->isSettled()) {
                return;
            }

            $callbackResponse = $this->getPaymentDriver()->callback($request, ['payment' => $this->payment]);

            if ($callbackResponse->isRejected()) {
                $this->rejectCallback($callbackResponse->getError());
                return;
            }

            $this->clearRedirect();
            $this->setGatewayOrderId($callbackResponse->getGatewayOrderId());

            if ($callbackResponse->getSuccess()) {
                if ($this->payment->refund) {
                    $refundParameters = [
                        'gateway_request_id' => Uuid::uuid4()->toString(),
                        'gateway_refunds_uuid' => Uuid::uuid4()->toString()
                    ];

                    $this->updatePayment($refundParameters);

                    $refundResponse = $this->getPaymentDriver()->refund($request, $this->payment, $refundParameters);

                    if (!$refundResponse->isSuccessful()) {
                        $this->setError($refundResponse->getMessage());
                    }

                    event(new PaymentSuccess($this->payment->user, $this->payment));
                } else {
                    $this->setSuccessful();
                }
            } else {
                $this->setError($callbackResponse->getError() ?? 'Payment failed');
            }
        });

        return $this;
    }

    public function callbackRefund(Request $request): self
    {
        $this->callbackRejected = false;

        if (
            !$request->has('requestId') || !$request->has('refundsUuid')
            || is_null($this->payment->gateway_request_id)
            || $request->get('requestId') !== $this->payment->gateway_request_id
            || $request->get('refundsUuid') !== $this->payment->gateway_refunds_uuid
        ) {
            $this->rejectCallback('Invalid callback refund parameters.');
            return $this;
        }

        $callbackResponse = $this->getPaymentDriver()->callbackRefund($request);

        if ($callbackResponse->getSuccess()) {
            $this->setRefunded();
        } else {
            $this->rejectCallback($callbackResponse->getError());
        }

        return $this;
    }

    public function isCallbackRejected(): bool
    {
        return $this->callbackRejected;
    }

    private function lockPayment(): void
    {
        if (!$this->payment->exists) {
            return;
        }

        $locked = Payment::query()->whereKey($this->payment->getKey())->lockForUpdate()->first();

        if ($locked) {
            $this->payment->setRawAttributes($locked->getAttributes(), true);
        }
    }

    /**
     * Paid, refunded or cancelled. A trial payment (refund flag) is settled once its refund was started.
     */
    private function isSettled(): bool
    {
        $status = $this->payment->status;

        if ($status instanceof PaymentStatus && $status->in([PaymentStatus::REFUNDED, PaymentStatus::CANCELLED])) {
            return true;
        }

        if ($this->payment->refund) {
            return !is_null($this->payment->gateway_request_id);
        }

        return $status instanceof PaymentStatus && $status->is(PaymentStatus::PAID);
    }

    private function rejectCallback(?string $reason): void
    {
        $this->callbackRejected = true;

        Log::warning('Payment callback rejected', [
            'payment_id' => $this->payment->getKey(),
            'driver' => $this->payment->driver,
            'reason' => $reason,
        ]);
    }

    private function setSuccessful(): void
    {
        $this->setPaymentStatus(PaymentStatus::PAID());
        event(new PaymentSuccess($this->payment->user, $this->payment));
    }

    private function clearRedirect(): void
    {
        $this->payment->redirect_url = null;
    }

    private function setRedirect(string $redirect_url): void
    {
        $this->payment->redirect_url = $redirect_url;
        $this->setPaymentStatus(PaymentStatus::REQUIRES_REDIRECT());
    }

    private function setCancelled(): void
    {
        $this->setPaymentStatus(PaymentStatus::CANCELLED());
        event(new PaymentCancelled($this->payment->user, $this->payment));
    }

    private function setRefunded(): void
    {
        $this->setPaymentStatus(PaymentStatus::REFUNDED());
    }

    private function setError(string $message, string $code = '0'): void
    {
        $this->setPaymentStatus(PaymentStatus::FAILED());
        event(new PaymentFailed($this->payment->user, $this->payment, $code, $message));
    }

    private function setRefund(array $parameters = []): void
    {
        if (isset($parameters['has_trial'])) {
            $this->payment->refund = $parameters['has_trial'] === true;
        }
    }

    public function isNew(): bool
    {
        return $this->getPayment()->status->is(PaymentStatus::NEW);
    }

    public function isSuccessful(): bool
    {
        return $this->getPayment()->status->is(PaymentStatus::PAID);
    }

    public function isRedirect(): bool
    {
        return $this->getPayment()->status->is(PaymentStatus::REQUIRES_REDIRECT());
    }

    public function getRedirectUrl(): string
    {
        return $this->getPayment()->redirect_url;
    }

    public function isCancelled(): bool
    {
        return $this->getPayment()->status->is(PaymentStatus::CANCELLED);
    }

    private function setPaymentStatus(PaymentStatus $status): bool
    {
        $this->payment->status = $status;
        return $this->payment->save();
    }

    protected function getPaymentDriver(): GatewayDriverContract
    {
        return PaymentGateway::driver($this->getPaymentDriverName());
    }
}
