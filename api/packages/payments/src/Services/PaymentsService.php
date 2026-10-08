<?php

namespace EscolaLms\Payments\Services;

use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Payments\Contracts\Payable;
use EscolaLms\Payments\Entities\PaymentsConfig;
use EscolaLms\Payments\Events\PaymentRegistered;
use EscolaLms\Payments\Facades\PaymentGateway;
use EscolaLms\Payments\Gateway\Drivers\RevenueCatDriver;
use EscolaLms\Payments\Models\Payment;
use EscolaLms\Payments\Repositories\Contracts\PaymentsRepositoryContract;
use EscolaLms\Payments\Services\Contracts\PaymentsServiceContract;
use EscolaLms\Payments\Entities\PaymentProcessor;
use EscolaLms\Payments\Gateway\Drivers\Przelewy24Driver;
use EscolaLms\Payments\Gateway\Drivers\StripeDriver;
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
