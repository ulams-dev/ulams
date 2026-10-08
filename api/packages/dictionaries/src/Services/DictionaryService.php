<?php

namespace Ulams\Dictionaries\Services;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Dictionaries\Dtos\DictionaryCriteriaDto;
use Ulams\Dictionaries\Dtos\DictionaryDto;
use Ulams\Dictionaries\Dtos\PageDto;
use Ulams\Dictionaries\Models\Dictionary;
use Ulams\Dictionaries\Repositories\Contracts\DictionaryRepositoryContract;
use Ulams\Dictionaries\Services\Contracts\DictionaryServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class DictionaryService implements DictionaryServiceContract
{

    public function __construct(private readonly DictionaryRepositoryContract $dictionaryRepository)
    {
    }

    public function list(DictionaryCriteriaDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator
    {
        return $this->dictionaryRepository->findAll(
            $criteriaDto->toArray(),
            $pageDto->getPerPage(),
            $orderDto->getOrder() ?? 'desc',
            $orderDto->getOrderBy() ?? 'id'
        );
    }

    public function create(DictionaryDto $dto): Dictionary
    {
        /** @var Dictionary */
        return $this->dictionaryRepository->create([
            ...$dto->toArray(),
            'slug' => $this->getSlug($dto->getName()),
        ]);
    }

    public function update(int $id, DictionaryDto $dto): Dictionary
    {
        /** @var Dictionary */
        return $this->dictionaryRepository->update($dto->toArray(), $id);
    }

    public function delete(Dictionary $dictionary): void
    {
        $this->dictionaryRepository->remove($dictionary);
    }

    private function getSlug(string $search): string
    {
        $slug = Str::slug($search);
        $exists = $this->dictionaryRepository->allQuery(['slug' => $slug])->exists();

        return $exists ? $slug . '-' . uniqid() : $slug;
    }
}
