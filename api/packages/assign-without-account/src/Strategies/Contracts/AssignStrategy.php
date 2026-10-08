<?php

namespace EscolaLms\AssignWithoutAccount\Strategies\Contracts;

use EscolaLms\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AssignStrategy
{
    public function getModelInstance(string $morphableType, int $morphableId): Model;

    public function assign(string $morphableType, int $morphableId, User $user): bool;

    public function dispatch(string $email, Model $model): void;
}
