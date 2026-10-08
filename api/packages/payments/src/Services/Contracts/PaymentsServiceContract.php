<?php

namespace Ulams\Payments\Services\Contracts;

use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Payments\Contracts\Payable;
use Ulams\Payments\Entities\PaymentsConfig;
use Ulams\Payments\Models\Payment;
use Ulams\Payments\Entities\PaymentProcessor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PaymentsServiceContract
{
    public function getPaymentsConfig(): PaymentsConfig;

    public function listEnabledGateways(): array;
    public function listGatewaysWithRequiredParameters(): array;
    public function isDriverEnabled(string $driver): bool;

    public function processPayable(Payable $payable): PaymentProcessor;
    public function processPayment(Payment $payment): PaymentProcessor;
    public function searchPayments(CriteriaDto $criteriaDto, OrderDto $orderDto): LengthAwarePaginator;
    public function listPaymentsForUser(int $user_id): Collection;
    public function findPayment(int $id): ?Payment;
    public function searchPaymentsForExport(CriteriaDto $criteriaDto, OrderDto $orderDto): Collection;
}
