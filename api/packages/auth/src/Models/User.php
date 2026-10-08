<?php

namespace Ulams\Auth\Models;

use Database\Factories\Ulams\Auth\Models\UserFactory;
use Ulams\Auth\Events\AccountBlocked;
use Ulams\Auth\Events\AccountDeleted;
use Ulams\Auth\Models\Traits\HasGroups;
use Ulams\Auth\Models\Traits\HasOnboardingStatus;
use Ulams\Auth\Models\Traits\UserHasSettings;
use Ulams\Categories\Models\Traits\HasInterests;
use Ulams\ModelFields\Traits\ModelFields;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     description="User model",
 *     title="User",
 *     required={},
 *     @OA\Xml(
 *         name="User"
 *     ),
 *     @OA\Property(
 *          type="integer",
 *          format="int64",
 *          property="id",
 *     ),
 *     @OA\Property(
 *          property="email",
 *          type="string"
 *     )
 * )
 *
 * @property string $first_name
 * @property ?string $delete_user_token
 * @property ?string $path_avatar
 * @property bool $is_active
 * @property ?string $password_reset_token
 * @property string $password
 */
class User extends \Ulams\Core\Models\User
{
    use HasInterests, HasOnboardingStatus, UserHasSettings, HasGroups, ModelFields, SoftDeletes;

    public const PASSWORD_RULES = [
        'required',
        'string',
        'min:6'
    ];

    protected function getTraitOwner(): self
    {
        return $this;
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function($user){
            event(new AccountDeleted($user));
        });

        static::updated(function($user){
            if ($user->wasChanged('is_active') && !$user->is_active) {
                event(new AccountBlocked($user));
            }
        });
    }
}
