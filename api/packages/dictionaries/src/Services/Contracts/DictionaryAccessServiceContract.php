<?php

namespace Ulams\Dictionaries\Services\Contracts;

use Ulams\Dictionaries\Dtos\DictionaryAccessDto;
use Ulams\Dictionaries\Models\Dictionary;
use Illuminate\Support\Collection;

interface DictionaryAccessServiceContract
{
    public function getByUserId(int $userId): Collection;
    public function getByDictionaryId(int $userId): Collection;
    public function setAccess(Dictionary $dictionary, DictionaryAccessDto $dto): void;
}
