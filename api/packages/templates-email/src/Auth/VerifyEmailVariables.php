<?php

namespace EscolaLms\TemplatesEmail\Auth;

use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

class VerifyEmailVariables extends CommonAuthVariables
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
                '&hash=' . sha1($notifiable->getEmailForVerification());
        }

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('Verify Email Address'),
            'content' => self::wrapWithMjml(
                '<mj-text>'
                    . '<p>'
                    . Lang::get('Please click the button below to verify your email address.')
                    . '</p>'
                    . '</mj-text>'
                    . '<mj-button href="' . self::VAR_ACTION_LINK . '">' . Lang::get('Verify Email Address') . '</mj-button>'
                    . '<mj-text>'
                    . '<p>'
                    . Lang::get('If you did not create an account, no further action is required.')
                    . '</p>'
                    . '</mj-text>'
            )
        ];
    }
}
