<?php

namespace EscolaLms\PencilSpaces\Models;

use EscolaLms\Core\Models\User as CoreUser;
use EscolaLms\PencilSpaces\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class User
 *
 * @package EscolaLms\PencilSpaces\Models
 *
 * @property int $id
 * @property string $name
 *
 * @property-read ?PencilSpaceAccount $pencilSpaceAccount
 */
class User extends CoreUser
{
    public function pencilSpaceAccount(): HasOne
    {
        return $this->hasOne(PencilSpaceAccount::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
