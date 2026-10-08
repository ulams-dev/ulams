<?php

namespace EscolaLms\Vouchers\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Core\Models\User;
use EscolaLms\Vouchers\Exceptions\CouponInactiveException;
use EscolaLms\Vouchers\Exceptions\CouponNotApplicableException;
use EscolaLms\Vouchers\Http\Controllers\Swagger\VouchersApiControllerSwagger;
use EscolaLms\Vouchers\Http\Requests\ApplyCouponRequest;
use EscolaLms\Vouchers\Http\Requests\UnapplyCouponRequest;
use EscolaLms\Vouchers\Services\Contracts\ShopServiceContract;
use Illuminate\Http\JsonResponse;

class VouchersApiController extends EscolaLmsBaseController implements VouchersApiControllerSwagger
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
