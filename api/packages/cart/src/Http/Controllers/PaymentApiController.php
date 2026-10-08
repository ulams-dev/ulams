<?php

namespace Ulams\Cart\Http\Controllers;

use Ulams\Cart\Http\Requests\PaymentCartRequest;
use Ulams\Cart\Http\Requests\PaymentProductRequest;
use Ulams\Cart\Http\Swagger\PaymentSwagger;
use Ulams\Cart\Services\Contracts\ShopServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Core\Models\User;
use Ulams\Payments\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;

class PaymentApiController extends UlamsBaseController implements PaymentSwagger
{
    protected ShopServiceContract $shopService;

    public function __construct(ShopServiceContract $shopService)
    {
        $this->shopService = $shopService;
    }

    public function pay(PaymentCartRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $cart = $this->shopService->cartForUser($user);

            $payment = $this->shopService->purchaseCart(
                $cart,
                $request->toClientDetailsDto(),
                $request->getAdditionalPaymentParameters()
            );

            return $this->sendResponseForResource(PaymentResource::make($payment), __('Payment created'));
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function payProduct(PaymentProductRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $payment = $this->shopService->purchaseProduct(
                $request->getProduct(),
                $user,
                $request->toClientDetailsDto(),
                $request->getAdditionalPaymentParameters()
            );

            return $this->sendResponseForResource(PaymentResource::make($payment), __('Payment created'));
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }
}
