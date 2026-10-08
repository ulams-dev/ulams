<?php

namespace EscolaLms\PencilSpaces\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\PencilSpaces\Models\User;
use EscolaLms\PencilSpaces\Repositories\Contracts\UserRepositoryContract;

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
