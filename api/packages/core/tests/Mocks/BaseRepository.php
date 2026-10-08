<?php

namespace Ulams\Core\Tests\Mocks;

use Ulams\Core\Models\User;
use Ulams\Core\Repositories\BaseRepository as CoreBaseRepository;

class BaseRepository extends CoreBaseRepository
{
    public function model(): string
    {
        return User::class;
    }

    public function getFieldsSearchable(): array
    {
        return [
            'first_name',
            'last_name',
            'email',
        ];
    }
}
