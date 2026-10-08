<?php

namespace Ulams\Tenancy\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Ulams\Tenancy\Models\Tenant;

/**
 * Derives tenant resource names (hosts, database, bucket, Redis prefix) and env values from
 * the slug and the `ulams_tenancy` naming patterns.
 */
class TenantNaming
{
    public const SLUG_PATTERN = '/^[a-z][a-z0-9]{1,29}$/';

    public const RESERVED_SLUGS = ['api', 'app', 'admin', 'www', 'storage', 'minio', 'ws', 'metrics', 'platform', 'default', 'test', 'postgres'];

    public static function assertValidSlug(string $slug): void
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            throw new InvalidArgumentException(
                "Invalid tenant slug '{$slug}': use 2-30 lowercase letters and digits, starting with a letter."
            );
        }
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new InvalidArgumentException("Tenant slug '{$slug}' is reserved.");
        }
    }

    public static function pattern(string $key, string $slug): string
    {
        return str_replace('{slug}', $slug, (string) config('ulams_tenancy.' . $key));
    }

    /**
     * Attributes of a tenant that has not been provisioned yet.
     */
    public static function newTenantAttributes(string $slug): array
    {
        self::assertValidSlug($slug);

        $database = self::pattern('database', $slug);

        return [
            'slug' => $slug,
            'name' => Str::title($slug),
            'api_host' => self::pattern('api_host', $slug),
            'front_host' => self::pattern('front_host', $slug),
            'admin_host' => self::pattern('admin_host', $slug),
            'db_name' => $database,
            'db_user' => $database,
            'db_password' => Str::random(40),
            'app_key' => 'base64:' . base64_encode(random_bytes(32)),
            'bucket' => self::pattern('bucket', $slug),
            'redis_prefix' => self::pattern('redis_prefix', $slug),
            'status' => Tenant::STATUS_PROVISIONING,
            'steps' => [],
        ];
    }

    public static function emailDomain(Tenant $tenant): string
    {
        return self::pattern('email_domain', $tenant->slug);
    }

    public static function adminEmail(Tenant $tenant): string
    {
        return 'admin@' . self::emailDomain($tenant);
    }

    /**
     * Values written to `.env.<api_host>` on top of the platform `.env`.
     *
     * @return array<string, string>
     */
    public static function envValues(Tenant $tenant): array
    {
        $prefix = $tenant->redis_prefix;

        return [
            'APP_NAME' => $tenant->name,
            'APP_URL' => $tenant->apiUrl(),
            'APP_KEY' => $tenant->app_key,
            'TENANT_SLUG' => $tenant->slug,
            'FRONTEND_URL' => $tenant->frontUrl(),
            'DB_DATABASE' => $tenant->db_name,
            'DB_USERNAME' => $tenant->db_user,
            'DB_PASSWORD' => $tenant->db_password,
            'FILESYSTEM_DRIVER' => 's3',
            'AWS_BUCKET' => $tenant->bucket,
            'AWS_URL' => rtrim((string) config('ulams_tenancy.storage_public_url'), '/') . '/' . $tenant->bucket,
            'REDIS_PREFIX' => $prefix,
            'CACHE_PREFIX' => $prefix . 'cache',
            'HORIZON_PREFIX' => $prefix . 'horizon:',
            'MAIL_FROM_ADDRESS' => 'no-reply@' . self::emailDomain($tenant),
            'MAIL_FROM_NAME' => $tenant->name,
            'INITIAL_USER_EMAIL' => self::adminEmail($tenant),
            'INITIAL_USER_PASSWORD' => (string) config('ulams_tenancy.demo_password'),
        ];
    }

    /**
     * Quotes a value for a dotenv file when it is not a plain token.
     */
    public static function envValue(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=,-]+$/', $value)) {
            return $value;
        }

        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }
}
