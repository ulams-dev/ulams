<?php

namespace Ulams\Dictionaries\Services;

use Ulams\Dictionaries\Dtos\DictionaryAccessDto;
use Ulams\Dictionaries\Models\Dictionary;
use Ulams\Dictionaries\Repositories\Contracts\DictionaryUserRepositoryContract;
use Ulams\Dictionaries\Services\Contracts\DictionaryAccessServiceContract;
use Illuminate\Support\Collection;

class DictionaryAccessService implements DictionaryAccessServiceContract
{
    public function __construct(private readonly DictionaryUserRepositoryContract $dictionaryUserRepository)
    {
    }

    public function getByUserId(int $userId): Collection
    {
        return $this->dictionaryUserRepository->allQuery()->where('user_id', $userId)->get();
    }

    public function getByDictionaryId(int $userId): Collection
    {
        return $this->dictionaryUserRepository->allQuery()->where('dictionary_id', $userId)->get();
    }

    public function setAccess(Dictionary $dictionary, DictionaryAccessDto $dto): void
    {
        $dictionary->users()->sync($dto->toArray());
    }
}
