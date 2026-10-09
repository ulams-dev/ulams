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
    'token_expiration_minutes' => TokenExpirationEnum::SHORT_TIME_IN_MINUTES,
    'version' => env('APP_VERSION', 'dev'),
    // scoped personal access tokens (ADR 0074)
    'max_tokens_per_user' => (int) env('AUTH_MAX_TOKENS_PER_USER', 50),
    'device_approve_per_minute' => (int) env('AUTH_DEVICE_APPROVE_PER_MINUTE', 5),
    'agent_audit_days' => (int) env('AUTH_AGENT_AUDIT_DAYS', 365),
];
