<?php

namespace Ulams\Auth\Services\Contracts;

use Ulams\Auth\Models\User;
use Laravel\Passport\PersonalAccessTokenResult;

interface AuthServiceContract
{
    public function forgotPassword(string $email, string $returnUrl): void;

    public function resetPassword(string $email, string $token, string $password): void;

    public function createTokenForUser(User $user, bool $rememberMe = false): PersonalAccessTokenResult;

    public function refreshToken(User $user): PersonalAccessTokenResult;
}
