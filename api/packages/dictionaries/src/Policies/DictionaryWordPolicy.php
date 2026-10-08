<?php

namespace Ulams\Dictionaries\Policies;

use Ulams\Auth\Models\User;
use Ulams\Dictionaries\Enums\DictionariesPermissionEnum;
use Ulams\Dictionaries\Models\DictionaryWord;
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
