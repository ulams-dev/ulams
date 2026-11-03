<?php

namespace Ulams\Auth\Services\Contracts;

use Laravel\Passport\PersonalAccessTokenResult;
use Ulams\Auth\Exceptions\InvalidReturnUrlException;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Exceptions\TokenExpiredException;

interface SocialAccountServiceContract
{
    /**
     * Starts a social login: validates the return URL against the tenant allow-list and returns the signed state.
     *
     * @throws InvalidReturnUrlException
     */
    public function startLogin(string $provider, string $host, ?string $returnUrl, string $challenge): string;

    /**
     * Finishes the provider's callback and returns the URL to send the browser to: the allow-listed
     * return URL with a one-time `code` (or an `error` reason), never a token.
     *
     * @throws LoginHandoffException when the state is missing, forged, expired or already used
     */
    public function handleCallback(string $provider, ?string $state, string $host, ?string $providerError = null): string;

    /**
     * Redeems a one-time code.
     *
     * @return array{complete: bool, pre_user_token?: string, token?: PersonalAccessTokenResult}
     * @throws LoginHandoffException
     */
    public function exchange(string $code, string $verifier, string $host): array;

    /**
     * @throws TokenExpiredException
     */
    public function completeData(string $token, string $email, ?string $returnUrl);
}
