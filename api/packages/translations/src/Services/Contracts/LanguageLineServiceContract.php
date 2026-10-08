<?php

namespace EscolaLms\Translations\Services\Contracts;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Translations\Dto\PublicTranslationListCriteriaDto;
use EscolaLms\Translations\Enum\ConstantEnum;
use EscolaLms\Translations\Models\LanguageLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface LanguageLineServiceContract
{
    public function getList(OrderDto $orderDto, array $search = []): Builder;
    public function getPublicLanguageLinesPaginatedList(PublicTranslationListCriteriaDto $dto, $perPage = ConstantEnum::PER_PAGE): LengthAwarePaginator|Collection;
    public function create(array $data): LanguageLine;
    public function update(LanguageLine $languageLine, array $data): LanguageLine;
    public function delete(LanguageLine $languageLine): bool;
}
