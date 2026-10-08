<?php

namespace Ulams\Cart\Http\Controllers;

use Ulams\Cart\Exceptions\InactiveSubscription;
use Ulams\Cart\Http\Requests\ProductableAttachRequest;
use Ulams\Cart\Http\Swagger\ProductablesSwagger;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Core\Models\User;
use Illuminate\Http\JsonResponse;

class ProductablesApiController extends UlamsBaseController implements ProductablesSwagger
{
    protected ProductServiceContract $productService;

    public function __construct(ProductServiceContract $productService)
    {
        $this->productService = $productService;
    }

    public function attach(ProductableAttachRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $activeSubscription = $this->productService->hasActiveSubscriptionAllIn($user);

        if (!$activeSubscription) {
            throw new InactiveSubscription();
        }

        $productable = $this->productService->findProductable($request->getProductableType(), $request->getProductableId());
        $this->productService->attachProductableToUser($productable, $request->getCartUser(), 1, $activeSubscription);

        return $this->sendSuccess(__('Productable attached to user'));
    }
}
