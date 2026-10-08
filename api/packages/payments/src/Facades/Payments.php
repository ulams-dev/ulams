<?php

namespace EscolaLms\Payments\Facades;

use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Payments\Contracts\Payable;
use EscolaLms\Payments\Entities\PaymentProcessor;
use EscolaLms\Payments\Entities\PaymentsConfig;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PaymentsConfig getPaymentsConfig()
 * 
 * @method static array listEnabledGateways()
 * @method static array listGatewaysWithRequiredParameters()
 * @method static bool isDriverEnabled(string $driver)
 * 
 * @method static PaymentProcessor processPayable(Payable $payable)
 * @method static PaymentProcessor processPayment(Payment $payment)
 * @method static Collection searchPayments(CriteriaDto $criteriaDto, OrderDto $orderDto)
 * @method static Collection listPaymentsForUser(int $user_id)
 * @method static Payment findPayment(int $id)
 * @method static Collection searchPaymentsForExport(CriteriaDto $criteriaDto, OrderDto $orderDto)
 *
 * @see \EscolaLms\Payments\Services\PaymentsService
 */
class Payments extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'payments';
    }
}
