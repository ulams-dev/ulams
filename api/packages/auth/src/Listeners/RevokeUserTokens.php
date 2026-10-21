<?php

namespace Ulams\Auth\Listeners;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Events\Auth;
use Ulams\Auth\Models\User;

/**
 * A deactivated or deleted user stops being signed in everywhere: every Passport access token
 * (login, remember-me, scoped API tokens, CLI device logins) and refresh token is revoked, and a
 * device login approved but not yet collected is cleared.
 */
class RevokeUserTokens
{
    public function handle(Auth $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $ids = $user->tokens()->pluck('id');
        if ($ids->isNotEmpty()) {
            $user->tokens()->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $ids)->update(['revoked' => true]);
        }

        DB::table('device_authorizations')
            ->where('user_id', $user->getKey())
            ->whereNotNull('access_token_encrypted')
            ->update(['access_token_encrypted' => null, 'status' => 'expired']);
    }
}
