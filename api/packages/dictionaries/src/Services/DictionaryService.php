<?php

namespace EscolaLms\Dictionaries\Services;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Dictionaries\Dtos\DictionaryCriteriaDto;
use EscolaLms\Dictionaries\Dtos\DictionaryDto;
use EscolaLms\Dictionaries\Dtos\PageDto;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Repositories\Contracts\DictionaryRepositoryContract;
use EscolaLms\Dictionaries\Services\Contracts\DictionaryServiceContract;
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
