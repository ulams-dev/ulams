<?php

use Ulams\Auth\Enums\SettingStatusEnum;
use Ulams\Auth\Enums\TokenExpirationEnum;

return [
    'superadmins' => [
        env('AUTH_SUPERADMIN_EMAIL'),
    ],
    'registration' => SettingStatusEnum::ENABLED,
    'account_must_be_enabled_by_admin' => SettingStatusEnum::DISABLED,
    'auto_verified_email' => SettingStatusEnum::DISABLED,
    'return_url' => null,
    'socialite_remember_me' => false,
    // Linking an EXISTING local account to a social login by e-mail address (ADR 0121). Only when the
    // provider says the address is verified, the address is in a domain listed here for that provider,
    // and the account is not an admin or tutor. Everything else asks the owner to sign in with the
    // password. Google is authoritative for gmail.com; add Google Workspace domains of your own
    // organisation with AUTH_SOCIAL_GOOGLE_TRUSTED_DOMAINS (comma separated). Facebook does not
    // report verification, so it never links by e-mail.
    'social_login' => [
        'trusted_domains' => [
            'google' => array_values(array_unique(array_merge(
                ['gmail.com'],
                array_filter(array_map('trim', explode(',', strtolower((string) env('AUTH_SOCIAL_GOOGLE_TRUSTED_DOMAINS', '')))))
            ))),
            'facebook' => [],
        ],
    ],
    'token_expiration_minutes' => TokenExpirationEnum::SHORT_TIME_IN_MINUTES,
    'version' => env('APP_VERSION', 'dev'),
    // scoped personal access tokens (ADR 0074)
    'max_tokens_per_user' => (int) env('AUTH_MAX_TOKENS_PER_USER', 50),
    'device_approve_per_minute' => (int) env('AUTH_DEVICE_APPROVE_PER_MINUTE', 5),
    'agent_audit_days' => (int) env('AUTH_AGENT_AUDIT_DAYS', 365),
];
