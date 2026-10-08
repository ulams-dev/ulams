<?php

namespace Ulams\Dictionaries\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Dictionaries\Dtos\DictionaryCriteriaDto;
use Ulams\Dictionaries\Dtos\DictionaryDto;
use Ulams\Dictionaries\Dtos\PageDto;
use Ulams\Dictionaries\Models\Dictionary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DictionaryServiceContract
{
    public function list(DictionaryCriteriaDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator;
    public function create(DictionaryDto $dto): Dictionary;
    public function update(int $id, DictionaryDto $dto): Dictionary;
    public function delete(Dictionary $dictionary): void;
}
