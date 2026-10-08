<?php

namespace Ulams\Core\Tests\Repositories;

use Ulams\Core\Models\User;
use Ulams\Core\Repositories\BaseRepository;
use Ulams\Core\Repositories\Traits\Activationable;

class UserRepository extends BaseRepository
{
    use Activationable;

    protected array $fieldSearchable = [];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return User::class;
    }
}
