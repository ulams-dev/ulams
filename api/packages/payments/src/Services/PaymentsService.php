<?php

namespace Ulams\Payments\Services;

use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Payments\Contracts\Payable;
use Ulams\Payments\Entities\PaymentsConfig;
use Ulams\Payments\Events\PaymentRegistered;
use Ulams\Payments\Facades\PaymentGateway;
use Ulams\Payments\Gateway\Drivers\RevenueCatDriver;
use Ulams\Payments\Models\Payment;
use Ulams\Payments\Repositories\Contracts\PaymentsRepositoryContract;
use Ulams\Payments\Services\Contracts\PaymentsServiceContract;
use Ulams\Payments\Entities\PaymentProcessor;
use Ulams\Payments\Gateway\Drivers\Przelewy24Driver;
use Ulams\Payments\Gateway\Drivers\StripeDriver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaymentsService implements PaymentsServiceContract
{
    private function repository(): PaymentsRepositoryContract
    {
        return app(PaymentsRepositoryContract::class);
    }

    public function getPaymentsConfig(): PaymentsConfig
    {
        return PaymentGateway::getPaymentsConfig();
    }

    public function listPaymentsForUser(int $user_id): Collection
    {
        return $this->repository()->allQuery()->where('user_id', $user_id)->get();
    }

    public function searchPayments(CriteriaDto $criteriaDto, OrderDto $orderDto): LengthAwarePaginator
    {
        return $this->repository()->searchAndOrder($criteriaDto, $orderDto)->paginate(request()->input('per_page', 15));
    }

    public function processPayable(Payable $payable): PaymentProcessor
    {
        /** @var Payment $payment */
        $payment = $this->repository()->create([
            'amount' => $payable->getPaymentAmount(),
            'currency' => $payable->getPaymentCurrency() ?? $this->getPaymentsConfig()->getDefaultCurrency(),
            'description' => $payable->getPaymentDescription(),
            'order_id' => $payable->getPaymentOrderId()
        ]);
        if ($payable instanceof Model) {
            $payment->payable()->associate($payable);
        }
        if ($payable->getUser()) {
            $payment->user()->associate($payable->getUser());
        }
        $payment->save();

        // Payment starts here, maybe this event fits here
        $this->dispatchRegisterPaymentEvent($payable->getUser(), $payment);
        return new PaymentProcessor($payment->refresh());
    }

    public function findPayment(int $id): ?Payment
    {
        return Payment::find($id);
    }

    public function processPayment(Payment $payment): PaymentProcessor
    {
        return new PaymentProcessor($payment);
    }

    public function dispatchRegisterPaymentEvent(Authenticatable $user, Payment $payment)
    {
        event(new PaymentRegistered($user, $payment));
    }

    public function listEnabledGateways(): array
    {
        return array_filter([
            'stripe' => $this->getPaymentsConfig()->isStripeEnabled(),
            'przelewy24' => $this->getPaymentsConfig()->isPrzelewy24Enabled(),
            'revenuecat' => $this->getPaymentsConfig()->isRevenueCatEnabled()
        ], fn (bool $enabled) => $enabled);
    }

    public function listGatewaysWithRequiredParameters(): array
    {
        return [
            'default_gateway' => $this->getPaymentsConfig()->getDefaultGateway(),
            'gateways' => [
                'stripe' => [
                    'enabled' => $this->getPaymentsConfig()->isStripeEnabled(),
                    'parameters' => StripeDriver::requiredParameters()
                ],
                'przelewy24' => [
                    'enabled' => $this->getPaymentsConfig()->isPrzelewy24Enabled(),
                    'parameters' => Przelewy24Driver::requiredParameters()
                ],
                'revenuecat' => [
                    'enabled' => $this->getPaymentsConfig()->isRevenueCatEnabled(),
                    'parameters' => RevenueCatDriver::requiredParameters()
                ]
            ]
        ];
    }

    public function isDriverEnabled(string $driver): bool
    {
        return array_key_exists($driver, $this->listEnabledGateways());
    }

    public function searchPaymentsForExport(CriteriaDto $criteriaDto, OrderDto $orderDto): Collection
    {
        return $this->repository()->searchAndOrder($criteriaDto, $orderDto)->get();
    }
}
