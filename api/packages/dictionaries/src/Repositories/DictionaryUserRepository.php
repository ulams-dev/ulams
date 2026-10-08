<?php

namespace Ulams\Dictionaries\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\Dictionaries\Models\DictionaryUser;
use Ulams\Dictionaries\Repositories\Contracts\DictionaryUserRepositoryContract;

class DictionaryUserRepository extends BaseRepository implements DictionaryUserRepositoryContract
{
    public function model(): string
    {
        return DictionaryUser::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }
}
