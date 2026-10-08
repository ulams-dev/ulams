<?php

namespace EscolaLms\Dictionaries\Services\Contracts;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Dictionaries\Dtos\DictionaryCriteriaDto;
use EscolaLms\Dictionaries\Dtos\DictionaryDto;
use EscolaLms\Dictionaries\Dtos\PageDto;
use EscolaLms\Dictionaries\Models\Dictionary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DictionaryServiceContract
{
    public function list(DictionaryCriteriaDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator;
    public function create(DictionaryDto $dto): Dictionary;
    public function update(int $id, DictionaryDto $dto): Dictionary;
    public function delete(Dictionary $dictionary): void;
}
