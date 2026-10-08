<?php

namespace Ulams\Dictionaries\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Dictionaries\Dtos\DictionaryWordCriteriaDto;
use Ulams\Dictionaries\Dtos\DictionaryWordDto;
use Ulams\Dictionaries\Dtos\PageDto;
use Ulams\Dictionaries\Models\DictionaryWord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

interface DictionaryWordServiceContract
{
    public function list(DictionaryWordCriteriaDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator;
    public function create(DictionaryWordDto $dto): DictionaryWord;
    public function update(int $id, DictionaryWordDto $dto): DictionaryWord;
    public function delete(DictionaryWord $dictionary): void;
    public function categories(DictionaryWordCriteriaDto $criteriaDto): Collection;

    /**
     * @throws TooManyRequestsHttpException
     */
    public function find(int $id, ?int $userId): DictionaryWord;
}
