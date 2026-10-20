<?php

namespace Ulams\LivingCourse\Connectors;

/** A connector whose credentials come from a "Connect your account" sign-in (see `Oauth\OAuthService`). */
interface OAuthConnector extends SourceConnector
{
    /** Key of the provider in `OAuthProviders`: `google` or `notion`. */
    public function oauthProvider(): string;
}
