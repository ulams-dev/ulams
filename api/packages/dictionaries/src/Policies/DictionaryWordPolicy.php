<?php

namespace EscolaLms\Dictionaries\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\Dictionaries\Enums\DictionariesPermissionEnum;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Auth\Access\HandlesAuthorization;

class DictionaryWordPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_WORD_CREATE);
    }

    public function list(User $user): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_WORD_LIST);
    }

    public function read(User $user, DictionaryWord $dictionaryWord): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_WORD_READ);
    }

    public function update(User $user, DictionaryWord $dictionaryWord): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_WORD_UPDATE);
    }

    public function delete(User $user, DictionaryWord $dictionaryWord): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_WORD_DELETE);
    }

    public function import(User $user): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_WORD_IMPORT);
    }
}
