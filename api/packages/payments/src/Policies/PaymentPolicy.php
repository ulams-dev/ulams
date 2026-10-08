<?php

namespace Ulams\Payments\Policies;

use Ulams\Core\Models\User;
use Ulams\Payments\Enums\PaymentsPermissionsEnum;
use Ulams\Payments\Models\Payment;
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
