<?php

namespace Ulams\Vouchers\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Core\Models\User;
use Ulams\Vouchers\Exceptions\CouponInactiveException;
use Ulams\Vouchers\Exceptions\CouponNotApplicableException;
use Ulams\Vouchers\Http\Controllers\Swagger\VouchersApiControllerSwagger;
use Ulams\Vouchers\Http\Requests\ApplyCouponRequest;
use Ulams\Vouchers\Http\Requests\UnapplyCouponRequest;
use Ulams\Vouchers\Services\Contracts\ShopServiceContract;
use Illuminate\Http\JsonResponse;

class VouchersApiController extends UlamsBaseController implements VouchersApiControllerSwagger
{
    protected ShopServiceContract $shopService;

    public function __construct(ShopServiceContract $shopService)
    {
        $this->shopService = $shopService;
    }

    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $cart = $this->shopService->cartForUser($user);
            $cartManager = $cart->cart_manager;
            $cartManager->setCoupon($request->getCoupon());
        } catch (CouponInactiveException $ex) {
            return $this->sendResponse(['code' => $request->getCoupon()->code], $ex->getMessage(), 400);
        } catch (CouponNotApplicableException $ex) {
            return $this->sendResponse(['code' => $request->getCoupon()->code], $ex->getMessage(), 400);
        }
        return $this->sendSuccess(__("Coupon added to cart"));
    }

    public function unapply(UnapplyCouponRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cart = $this->shopService->cartForUser($user);
        $cartManager = $cart->cart_manager;
        $cartManager->removeCoupon();
        return $this->sendSuccess(__("Coupon removed from cart"));
    }
}
