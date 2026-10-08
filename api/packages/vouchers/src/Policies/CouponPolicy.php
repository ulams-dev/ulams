<?php

namespace Ulams\Vouchers\Policies;

use Ulams\Core\Models\User;
use Ulams\Vouchers\Enums\VoucherPermissionsEnum;
use Ulams\Vouchers\Models\Coupon;
use Ulams\Vouchers\Services\Contracts\CouponServiceContract;
use Illuminate\Auth\Access\HandlesAuthorization;

class CouponPolicy
{
    use HandlesAuthorization;

    protected CouponServiceContract $couponService;

    public function __construct(CouponServiceContract $couponService)
    {
        $this->couponService = $couponService;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(VoucherPermissionsEnum::COUPON_LIST);
    }

    public function view(User $user, Coupon $course): bool
    {
        return $user->can(VoucherPermissionsEnum::COUPON_READ);
    }

    public function create(User $user): bool
    {
        return $user->can(VoucherPermissionsEnum::COUPON_CREATE);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->can(VoucherPermissionsEnum::COUPON_UPDATE);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->can(VoucherPermissionsEnum::COUPON_DELETE);
    }

    public function apply(User $user, Coupon $coupon): bool
    {
        return $user->can(VoucherPermissionsEnum::COUPON_USE);
    }

    public function unapply(User $user): bool
    {
        return $user->can(VoucherPermissionsEnum::COUPON_USE);
    }
}
