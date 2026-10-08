<?php

namespace EscolaLms\AssignWithoutAccount\Strategies;

use EscolaLms\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use EscolaLms\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

abstract class AbstractAssignStrategy implements AssignStrategy
{
    protected function createUser(string $email): User
    {
        $user = new User();
        $user->email = $email;

        return $user;
    }

    protected function findModel(string $namespace, int $id): ?Model
    {
        return $namespace::find($id);
    }

    public function getModelInstance(string $namespace, int $id): Model
    {
        $model = $this->findModel($namespace, $id);

        if (!$model) {
            throw new InvalidArgumentException(__('Model not found!'));
        }

        return $model;
    }
}
