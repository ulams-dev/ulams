<?php

namespace Ulams\Auth\Services\Contracts;

use Ulams\Auth\Exceptions\TokenExpiredException;

interface SocialAccountServiceContract
{
    public function getReturnUrl(string $provider, ?string $returnUrl, ?string $state): string;

    /**
     * @throws TokenExpiredException
     */
    public function completeData(string $token, string $email, ?string $returnUrl);
}
