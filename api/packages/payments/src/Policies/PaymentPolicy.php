<?php

namespace EscolaLms\Payments\Policies;

use EscolaLms\Core\Models\User;
use EscolaLms\Payments\Enums\PaymentsPermissionsEnum;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view($user, Payment $payment)
    {
        if ($user->hasRole('admin') || $user->can(PaymentsPermissionsEnum::PAYMENTS_READ)) {
            return true;
        }

        if ($user->getKey() === $payment->user->getKey()) {
            return true;
        };

        return false;
    }

    public function export($user): bool
    {
        return $user->can(PaymentsPermissionsEnum::PAYMENTS_EXPORT);
    }
}
