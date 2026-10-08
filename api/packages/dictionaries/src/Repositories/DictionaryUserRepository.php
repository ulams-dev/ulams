<?php

namespace EscolaLms\Dictionaries\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Dictionaries\Models\DictionaryUser;
use EscolaLms\Dictionaries\Repositories\Contracts\DictionaryUserRepositoryContract;

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
