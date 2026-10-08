<?php

namespace Ulams\AssignWithoutAccount\Strategies;

use Ulams\AssignWithoutAccount\Events\AssignToProduct;
use Ulams\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Illuminate\Database\Eloquent\Model;
use Ulams\Core\Models\User;

class AssignProductStrategy extends AbstractAssignStrategy implements AssignStrategy
{
    private ProductServiceContract $productService;

    public function __construct(ProductServiceContract $productService)
    {
        $this->productService = $productService;
    }

    public function assign(string $morphableType, int $morphableId, User $user): bool
    {
        $model = $this->getModelInstance($morphableType, $morphableId);

        if (!$model) {
            return false;
        }

        $this->productService->attachProductToUser($model, $user);

        return true;
    }

    public function dispatch(string $email, Model $model): void
    {
        $user = $this->createUser($email);
        AssignToProduct::dispatch($user, $model);
    }
}
