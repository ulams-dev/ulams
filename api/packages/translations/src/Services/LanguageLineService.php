<?php

namespace EscolaLms\Translations\Services;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Translations\Dto\PublicTranslationListCriteriaDto;
use EscolaLms\Translations\Models\LanguageLine;
use EscolaLms\Translations\Enum\ConstantEnum;
use EscolaLms\Translations\Repositories\Contracts\LanguageLineRepositoryContract;
use EscolaLms\Translations\Repositories\Criteria\OrderCriterion;
use EscolaLms\Translations\Services\Contracts\LanguageLineServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LanguageLineService implements LanguageLineServiceContract
{
    private LanguageLineRepositoryContract $languageLineRepository;

    public function __construct(LanguageLineRepositoryContract $lineRepository)
    {
        $this->languageLineRepository = $lineRepository;
    }

    public function getList(OrderDto $orderDto, array $search = []): Builder
    {
        $criteria = $this->prepareCriteria($orderDto);

        return $this->languageLineRepository
            ->allQueryBuilder($search, $criteria);
    }

    public function getPublicLanguageLinesPaginatedList(PublicTranslationListCriteriaDto $searchDto, $perPage = ConstantEnum::PER_PAGE): LengthAwarePaginator|Collection
    {
        $result = $this->languageLineRepository
            ->allQueryBuilder([], $searchDto->toArray());
        return $perPage <= 0 ? $result->get() : $result->paginate($perPage);
    }

    public function create(array $data): LanguageLine
    {
        return $this->languageLineRepository->create($data);
    }

    public function update(LanguageLine $languageLine, array $data): LanguageLine
    {
        return $this->languageLineRepository->update($data, $languageLine->getKey());
    }

    public function delete(LanguageLine $languageLine): bool
    {
        return $this->languageLineRepository->delete($languageLine->getKey()) ?? false;
    }

    private function prepareCriteria(OrderDto $orderDto): array
    {
        $criteria = [];

        if (!is_null($orderDto->getOrder())) {
            $criteria[] = new OrderCriterion($orderDto->getOrderBy(), $orderDto->getOrder());
        }

        return $criteria;
    }
}
