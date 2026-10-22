<?php

namespace Ulams\Identity\Support;

use Illuminate\Database\Eloquent\Builder;
use Ulams\Auth\Models\User;

/**
 * The user model the application authenticates with (`auth.providers.users.model`: App\Models\User in the
 * API, a test model in package tests). Tokens can only be issued for that class, so every lookup goes through it.
 */
final class Users
{
    /** @return class-string<User> */
    public static function model(): string
    {
        return (string) config('auth.providers.users.model', User::class);
    }

    /** @return Builder<User> including soft-deleted users */
    public static function withTrashed(): Builder
    {
        return self::model()::withTrashed();
    }

    public static function find(int|string $id): ?User
    {
        return self::model()::query()->find($id);
    }
}
