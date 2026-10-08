<?php

namespace Ulams\Dictionaries\Policies;

use Ulams\Auth\Models\User;
use Ulams\Dictionaries\Enums\DictionariesPermissionEnum;
use Ulams\Dictionaries\Models\Dictionary;
use Illuminate\Auth\Access\HandlesAuthorization;

class DictionaryPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_CREATE);
    }

    public function list(User $user): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_LIST);
    }

    public function read(User $user, Dictionary $dictionary): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_READ);
    }

    public function update(User $user, Dictionary $dictionary): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_UPDATE);
    }

    public function delete(User $user, Dictionary $dictionary): bool
    {
        return $user->can(DictionariesPermissionEnum::DICTIONARY_DELETE);
    }
}
