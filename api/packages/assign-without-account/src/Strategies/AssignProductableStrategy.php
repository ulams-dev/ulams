<?php

namespace EscolaLms\AssignWithoutAccount\Strategies;

use EscolaLms\AssignWithoutAccount\Events\AssignToProductable;
use EscolaLms\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use EscolaLms\Cart\Services\Contracts\ProductServiceContract;
use EscolaLms\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AssignProductableStrategy extends AbstractAssignStrategy implements AssignStrategy
{
    private ProductServiceContract $productService;

    public function __construct(ProductServiceContract $productService)
    {
        $this->productService = $productService;
    }

    public function assign(string $morphableType, int $morphableId, User $user): bool
    {
        $model = $this->getModelInstance($morphableType, $morphableId);

        $this->productService->attachProductableToUser($model, $user);

        return true;
    }

    public function dispatch(string $email, Model $model): void
    {
        $user = $this->createUser($email);
        AssignToProductable::dispatch($user, $model);
    }

    public function getModelInstance(string $namespace, int $id): Model
    {
        if (!$this->productService->isProductableClassRegistered($namespace)) {
            throw new InvalidArgumentException(__('Productable class is not registered'));
        }

        $model = $this->findModel($namespace, $id);

        if (!$model) {
            throw new InvalidArgumentException(__('Model not found'));
        }

        return $model;
    }
}
