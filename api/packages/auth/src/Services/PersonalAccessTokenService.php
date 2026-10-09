<?php

namespace Ulams\Auth\Services;

use DateInterval;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Auth\Support\IssuedToken;
use Ulams\Auth\Support\TokenScopes;
use Ulams\Auth\UlamsAuthServiceProvider;

/**
 * Scoped personal access tokens on top of Passport (ADR 0074). The signed token string is
 * returned once with the `ulams_pat_` prefix; only Passport's token id and our metadata row are
 * stored, so there is nothing to leak from the database that can be replayed as a credential.
 */
class PersonalAccessTokenService implements PersonalAccessTokenServiceContract
{
    public const PREFIX = 'ulams_pat_';

    public function issue(
        User $user,
        string $name,
        array $scopes,
        int $expiresInDays,
        string $kind = 'cli',
        ?string $agentName = null,
        string $createdVia = 'admin',
        ?int $rateLimitPerMinute = null,
        ?array $callerScopes = null,
    ): IssuedToken {
        $scopes = TokenScopes::normalize($scopes);
        if (!in_array($kind, ApiTokenMeta::KINDS, true)) {
            throw new InvalidArgumentException("Unknown token kind {$kind}.");
        }
        if (!in_array($createdVia, ApiTokenMeta::CREATED_VIA, true)) {
            throw new InvalidArgumentException("Unknown creation channel {$createdVia}.");
        }
        $max = TokenScopes::maxDays();
        if ($expiresInDays < 1 || $expiresInDays > $max) {
            throw new InvalidArgumentException("A token lives 1 to {$max} days on this host.");
        }
        if (TokenScopes::hasPlatformScope($scopes) && !TokenScopes::isPlatformHost()) {
            throw new InvalidArgumentException('Platform scopes can only be granted on a platform host.');
        }
        if ($callerScopes !== null && !TokenScopes::covers($callerScopes, $scopes)) {
            throw new InvalidArgumentException('A token cannot grant scopes its own token lacks.');
        }
        $limit = (int) config(UlamsAuthServiceProvider::CONFIG_KEY . '.max_tokens_per_user', 50);
        $active = ApiTokenMeta::query()->whereHas('token', fn ($q) => $q
            ->where('user_id', $user->getKey())->where('revoked', false)->where('expires_at', '>', now()))->count();
        if ($active >= $limit) {
            throw new InvalidArgumentException("At most {$limit} active tokens per user; revoke one first.");
        }

        $previous = Passport::$personalAccessTokensExpireIn;
        try {
            Passport::personalAccessTokensExpireIn(now()->addDays($expiresInDays));

            return DB::transaction(function () use ($user, $name, $scopes, $kind, $agentName, $createdVia, $rateLimitPerMinute) {
                $result = $user->createToken(mb_substr($name, 0, 100), $scopes);
                $meta = ApiTokenMeta::query()->create([
                    'token_id' => $result->token->getKey(),
                    'kind' => $kind,
                    'agent_name' => $agentName !== null ? mb_substr($agentName, 0, 100) : null,
                    'created_via' => $createdVia,
                    'rate_limit_per_minute' => $rateLimitPerMinute,
                ]);

                return new IssuedToken(self::PREFIX . $result->accessToken, $result->token, $meta->setRelation('token', $result->token));
            });
        } finally {
            Passport::$personalAccessTokensExpireIn = $previous instanceof DateInterval ? $previous : null;
        }
    }

    public function listFor(User $user, bool $includeRevoked = false): Collection
    {
        return $this->query($includeRevoked)
            ->whereHas('token', fn ($q) => $q->where('user_id', $user->getKey()))
            ->get();
    }

    public function paginateAll(?int $userId, ?string $kind, bool $includeRevoked, int $perPage): LengthAwarePaginator
    {
        $query = $this->query($includeRevoked);
        if ($userId !== null) {
            $query->whereHas('token', fn ($q) => $q->where('user_id', $userId));
        }
        if ($kind !== null) {
            $query->where('kind', $kind);
        }

        return $query->paginate($perPage);
    }

    public function find(string $tokenId): ?ApiTokenMeta
    {
        return ApiTokenMeta::query()->with('token')->where('token_id', $tokenId)->first();
    }

    public function revoke(ApiTokenMeta $meta): void
    {
        Token::query()->whereKey($meta->token_id)->update(['revoked' => true]);
    }

    private function query(bool $includeRevoked)
    {
        $query = ApiTokenMeta::query()->with('token')->orderByDesc('id');
        if (!$includeRevoked) {
            $query->whereHas('token', fn ($q) => $q->where('revoked', false)->where('expires_at', '>', now()));
        }

        return $query;
    }
}
