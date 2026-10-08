<?php

namespace EscolaLms\TemplatesEmail\Auth;

use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Support\Str;
use EscolaLms\Auth\Repositories\Contracts\UserRepositoryContract;
use EscolaLms\Core\Models\User;
use Illuminate\Support\Facades\Lang;

class ResetPasswordVariables extends CommonAuthVariables
{
    const VAR_ACTION_LINK_EXPIRATION = "@VarActionLinkExpiration";

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables($user), [
            self::VAR_ACTION_LINK_EXPIRATION => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire'),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_ACTION_LINK_EXPIRATION => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire'),
        ]);
    }

    public static function getActionLink(EventWrapper $event): string
    {
        $notifiable = $event->getUser();

        $token = Str::random(32);
        app(UserRepositoryContract::class)->update([
            'password_reset_token' => $token,
        ], $notifiable->getKey());

        try {
            $url = $event->getReturnUrl();
        } catch (\Throwable $th) {
            $url = null;
        }

        if (!empty($url)) {
            return $url .
                '?email=' . $notifiable->getEmailForPasswordReset() .
                '&token=' . $token;
        }
        return url(route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('Reset Password Notification'),
            'content' => self::wrapWithMjml(
                '<mj-text>'
                    . '<p>'
                    . Lang::get('You are receiving this email because we received a password reset request for your account.')
                    . '</p>'
                    . '</mj-text>'
                    . '<mj-button href="' . self::VAR_ACTION_LINK . '">' . Lang::get('Reset Password') . '</mj-button>'
                    . '<mj-text>'
                    . '<p>'
                    . Lang::get('This password reset link will expire in :count minutes.', ['count' => self::VAR_ACTION_LINK_EXPIRATION])
                    . '</p>'
                    . '<p>'
                    . Lang::get('If you did not request a password reset, no further action is required.')
                    . '</p>'
                    . '</mj-text>'
            )
        ];
    }
}
