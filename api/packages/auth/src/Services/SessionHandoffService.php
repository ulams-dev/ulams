<?php

namespace Ulams\Auth\Services;

use Illuminate\Support\Facades\Log;
use Laravel\Passport\PersonalAccessTokenResult;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Models\LoginHandoffCode;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\AuthServiceContract;
use Ulams\Core\Support\EntryFronts;

/**
 * Moving a signed-in learner between the hosts of one academy (ADR 0195). Every host keeps its own `__Host-` session,
 * so a session cannot be shared: the source host asks for a one-time code (60 seconds, single use, bound to the user,
 * to the target host and to the S256 challenge of the browser at the target), and the target host redeems it with its
 * verifier and gets a session token of its own. The target must be the academy's own front or one of its active entry
 * hosts, on issue and again on redemption, so a host that was disabled in between gets nothing. No token ever appears
 * in a URL: only the code does.
 */
class SessionHandoffService
{
    public function __construct(
        private readonly LoginHandoffService $handoff,
        private readonly EntryFronts $fronts,
        private readonly AuthServiceContract $auth,
    ) {
    }

    /**
     * @throws LoginHandoffException when the host is not a front of this academy or the challenge is malformed
     */
    public function issue(User $user, string $host, string $challenge): string
    {
        $host = self::normalizeHost($host);
        if (!preg_match(LoginHandoffService::CHALLENGE_PATTERN, $challenge)) {
            throw new LoginHandoffException(LoginHandoffException::MALFORMED);
        }
        if (!$this->fronts->isFrontHost($host)) {
            throw new LoginHandoffException(LoginHandoffException::WRONG_HOST);
        }
        if (!$user->is_active) {
            throw new LoginHandoffException(LoginHandoffException::UNKNOWN);
        }

        // who, where to, never the code or a token
        Log::info('session hand-off issued', ['user' => $user->getKey(), 'host' => $host]);

        return $this->handoff->issueHandoffCode((int) $user->getKey(), $host, $challenge);
    }

    /** @throws LoginHandoffException for every reason the code cannot be used; the reason is for logs, never for the browser */
    public function exchange(string $code, string $verifier, string $host): PersonalAccessTokenResult
    {
        $host = self::normalizeHost($host);
        $row = $this->handoff->redeemCode($code, $verifier, $host);
        if ($row->kind !== LoginHandoffCode::KIND_HANDOFF) {
            // a code of a social or single sign-on login is not a hand-off code
            throw new LoginHandoffException(LoginHandoffException::UNKNOWN);
        }
        // the entry point may have been disabled or deleted since the code was issued
        if (!$this->fronts->isFrontHost($host)) {
            throw new LoginHandoffException(LoginHandoffException::WRONG_HOST);
        }

        $userClass = config('auth.providers.users.model');
        /** @var User|null $user */
        $user = $row->user_id ? $userClass::query()->find($row->user_id) : null;
        if (!$user || !$user->is_active || !$user->hasVerifiedEmail()) {
            throw new LoginHandoffException(LoginHandoffException::UNKNOWN);
        }

        return $this->auth->createTokenForUser($user, false);
    }

    /** A host name as the front sends it: lower case, without a port or a trailing dot. */
    public static function normalizeHost(string $host): string
    {
        return rtrim(strtolower((string) preg_replace('/:\d+$/', '', trim($host))), '.');
    }
}
