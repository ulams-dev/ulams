<?php

namespace Ulams\LivingCourse\Oauth;

/** What it takes to sign a user in with one vendor and to turn the answer into the secrets of a connection. */
final class OAuthProvider
{
    /**
     * @param string[] $scopes
     * @param array<string,string> $authorizeParams fixed extra parameters of the authorize URL
     * @param 'form'|'basic-json' $tokenStyle Google posts a form with the client secret; Notion posts JSON with HTTP basic auth
     * @param string $secretField the secret of the connector this provider fills
     * @param string $tokenField the field of the token answer that becomes the secret
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $connector,
        public readonly string $authorizeUrl,
        public readonly string $tokenUrl,
        public readonly array $scopes,
        public readonly array $authorizeParams,
        public readonly string $tokenStyle,
        public readonly string $secretField,
        public readonly string $tokenField,
    ) {
    }

    public static function google(): self
    {
        return new self('google', 'Google', 'google-drive', 'https://accounts.google.com/o/oauth2/v2/auth', 'https://oauth2.googleapis.com/token',
            ['https://www.googleapis.com/auth/drive.readonly'], ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'], 'form', 'refresh_token', 'refresh_token');
    }

    public static function notion(): self
    {
        return new self('notion', 'Notion', 'notion', 'https://api.notion.com/v1/oauth/authorize', 'https://api.notion.com/v1/oauth/token',
            [], ['owner' => 'user'], 'basic-json', 'token', 'access_token');
    }

    public function clientId(): string
    {
        return (string) config("living_course.oauth.{$this->key}.client_id", '');
    }

    public function clientSecret(): string
    {
        return (string) config("living_course.oauth.{$this->key}.client_secret", '');
    }

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }
}
