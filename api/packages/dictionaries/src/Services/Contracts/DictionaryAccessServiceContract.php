<?php

namespace EscolaLms\Dictionaries\Services\Contracts;

use EscolaLms\Dictionaries\Dtos\DictionaryAccessDto;
use EscolaLms\Dictionaries\Models\Dictionary;
use Illuminate\Support\Collection;

interface DictionaryAccessServiceContract
{
    public function getByUserId(int $userId): Collection;
    public function getByDictionaryId(int $userId): Collection;
    public function setAccess(Dictionary $dictionary, DictionaryAccessDto $dto): void;
}
