<?php

namespace EscolaLms\TemplatesEmail\Auth;

use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

class AccountDeletionRequestedVariables extends CommonAuthVariables
{
    public static function getActionLink(EventWrapper $event): string
    {
        $notifiable = $event->getUser();

        try {
            $url = $event->getReturnUrl();
        } catch (\Throwable $th) {
            $url = null;
        }

        if (!empty($url)) {
            return $url .
                '?id=' . $notifiable->getKey() .
                '&token=' . $event->getToken();
        }

        return URL::temporarySignedRoute(
            'profile.delete.confirmation',
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'userId' => $notifiable->getKey(),
                'token' => $event->getToken(),
            ]
        );
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('Confirmation of account deletion'),
            'content' => self::wrapWithMjml(
                '<mj-text>'
                . '<p>'
                . Lang::get('Please click the button below to delete account.')
                . '</p>'
                . '</mj-text>'
                . '<mj-button href="' . self::VAR_ACTION_LINK . '">' . Lang::get('Confirm account deletion') . '</mj-button>'
                . '<mj-text>'
                . '<p>'
                . Lang::get('If you did not delete an account, no further action is required.')
                . '</p>'
                . '</mj-text>'
            )
        ];
    }
}
