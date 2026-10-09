<?php

namespace Ulams\Cart\Http\Controllers;

use Ulams\Cart\Enums\ProductType;
use Ulams\Cart\Http\Requests\PaymentCartRequest;
use Ulams\Cart\Http\Requests\PaymentProductRequest;
use Ulams\Cart\Http\Swagger\PaymentSwagger;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Services\Contracts\ShopServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Core\Models\User;
use Ulams\Payments\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;

class PaymentApiController extends UlamsBaseController implements PaymentSwagger
{
    protected ShopServiceContract $shopService;

    protected ProductServiceContract $productService;

    public function __construct(ShopServiceContract $shopService, ProductServiceContract $productService)
    {
        $this->shopService = $shopService;
        $this->productService = $productService;
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
            $product = $request->getProduct();

            if (!$this->canPayForProduct($product, $user)) {
                return $this->sendError(__('Product is not available for purchase'), 403);
            }

            $payment = $this->shopService->purchaseProduct(
                $product,
                $user,
                $request->toClientDetailsDto(),
                $request->getAdditionalPaymentParameters()
            );

            return $this->sendResponseForResource(PaymentResource::make($payment), __('Payment created'));
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Same rules as adding the product to the cart. A subscription the user already owns stays payable as long
     * as it is purchasable: paying again is how a trial converts and how a lapsed subscription is renewed, and
     * the per-user limit would otherwise count the subscription against itself.
     */
    private function canPayForProduct(Product $product, User $user): bool
    {
        if ($this->productService->productIsBuyableByUser($product, $user, false, 1)) {
            return true;
        }

        return $product->purchasable
            && ProductType::isSubscriptionType($product->type)
            && $this->productService->productIsOwnedByUser($product, $user);
    }
}
