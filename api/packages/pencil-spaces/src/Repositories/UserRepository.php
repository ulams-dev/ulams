<?php

namespace Ulams\PencilSpaces\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\PencilSpaces\Models\User;
use Ulams\PencilSpaces\Repositories\Contracts\UserRepositoryContract;

class UserRepository extends BaseRepository implements UserRepositoryContract
{
    public function model(): string
    {
        return User::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function findById(int $id): User
    {
        /** @var User */
        return $this->model->newQuery()
            ->findOrFail($id)
            ->load('pencilSpaceAccount');
    }
}
