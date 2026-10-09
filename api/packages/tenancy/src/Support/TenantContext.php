<?php

namespace Ulams\Tenancy\Support;

/**
 * Tells whether the current process serves the platform or a tenant.
 */
class TenantContext
{
    public static function slug(): ?string
    {
        return config('ulams_tenancy.tenant_slug') ?: null;
    }

    public static function isPlatform(): bool
    {
        return self::slug() === null;
    }
}
