<?php

namespace Ulams\Tenancy\Services;

use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Ulams\Tenancy\Support\PassportKeyPermissions;

/**
 * Least-privilege configuration for the H5P service (api/h5p) in production.
 *
 * In development the service mounts the whole `api/` directory read-only and reads the Laravel
 * env files and Passport public keys from it. That also exposes the application code, every
 * secret of every env file (APP_KEY, mail and payment credentials) and the Passport private
 * keys. With `H5P_SERVICE_CONFIG_DIR` set, this exporter writes a directory holding only what
 * the service reads, and the service mounts that directory instead:
 *
 *   <dir>/.env                                   platform: the keys in self::KEYS
 *   <dir>/.env.<host>                            each tenant: the keys in self::KEYS
 *   <dir>/keys/oauth-public.key                  platform Passport public key
 *   <dir>/keys/<host_with_underscores>/oauth-public.key
 *
 * (ENV_DIR=<mount>, KEYS_DIR=<mount>/keys in the service.) Files are written atomically
 * (temp file + rename) because the service re-reads them while running. Without the setting
 * every method is a no-op.
 */
class H5PServiceConfigExporter
{
    /** Env keys the H5P service reads from a tenant file (api/h5p/src/tenancy/EnvFileTenantResolver.ts). */
    public const KEYS = [
        'APP_URL',
        'FRONTEND_URL',
        'ADMIN_URL',
        'TENANT_SLUG',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'AWS_ENDPOINT',
        'AWS_DEFAULT_REGION',
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_BUCKET',
        'AWS_USE_PATH_STYLE_ENDPOINT',
        'PASSPORT_PUBLIC_KEY',
        'H5P_INTERNAL_TOKEN',
    ];

    private const FILE_MODE = 0640;

    public function __construct(
        private Filesystem $files,
        private string $envDir,
        private string $platformStorage,
        private ?string $exportDir,
    ) {
    }

    public function enabled(): bool
    {
        return $this->exportDir !== null && $this->exportDir !== '';
    }

    public function exportPlatform(): void
    {
        if (!$this->enabled()) {
            return;
        }
        $this->writeEnv('.env', $this->envDir . '/.env');
        $this->copyKey($this->platformStorage, 'keys');
    }

    /**
     * @param string $storagePath the tenant's storage directory (its Passport keys)
     */
    public function exportTenant(string $host, string $storagePath): void
    {
        if (!$this->enabled()) {
            return;
        }
        $this->assertHost($host);
        $this->writeEnv('.env.' . $host, $this->envDir . '/.env.' . $host);
        $this->copyKey($storagePath, 'keys/' . str_replace('.', '_', $host));
    }

    public function remove(string $host): void
    {
        if (!$this->enabled()) {
            return;
        }
        $this->assertHost($host);
        $this->files->delete($this->path('.env.' . $host));
        $this->files->deleteDirectory($this->path('keys/' . str_replace('.', '_', $host)));
    }

    /**
     * @return array<string, string> the exported subset of an env file
     */
    public static function filter(string $contents): array
    {
        $vars = Dotenv::parse($contents);

        return array_filter(
            array_intersect_key($vars, array_flip(self::KEYS)),
            fn ($value) => $value !== null,
        );
    }

    private function writeEnv(string $name, string $source): void
    {
        if (!$this->files->isFile($source)) {
            throw new RuntimeException("Env file {$source} does not exist");
        }
        $lines = [];
        foreach (self::filter($this->files->get($source)) as $key => $value) {
            $lines[] = $key . '=' . $this->quote((string) $value);
        }
        $this->atomicPut($this->path($name), implode("\n", $lines) . "\n");
    }

    private function copyKey(string $storagePath, string $target): void
    {
        $source = rtrim($storagePath, '/') . '/' . PassportKeyPermissions::PUBLIC_KEY;
        if (!$this->files->isFile($source)) {
            return;
        }
        $this->atomicPut($this->path($target . '/' . PassportKeyPermissions::PUBLIC_KEY), $this->files->get($source));
    }

    private function atomicPut(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($path), 0750);
        if ($this->files->isFile($path) && $this->files->get($path) === $contents) {
            return;
        }
        $temp = $path . '.tmp-' . bin2hex(random_bytes(4));
        $this->files->put($temp, $contents);
        @chmod($temp, self::FILE_MODE);
        if (!@rename($temp, $path)) {
            $this->files->delete($temp);
            throw new RuntimeException("Cannot write {$path}");
        }
        $this->handOver($path);
    }

    /**
     * `ulams:h5p:export-config` and the tenant commands usually run as root (`docker compose exec`).
     * The service reads the files through its storage group (`group_add` in docker-compose.yml), so a
     * root-owned 0640 file or 0750 directory would be unreadable. As root, give the file and its
     * directories up to the export directory to the owner and group of the Laravel storage directory
     * (the php-fpm user, like StorageOwnership does for tenant storage).
     */
    private function handOver(string $path): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return;
        }
        $owner = @fileowner($this->platformStorage);
        $group = @filegroup($this->platformStorage);
        if ($owner === false || $owner === 0) {
            return;
        }

        $root = rtrim((string) $this->exportDir, '/');
        for ($item = $path; str_starts_with($item, $root) && strlen($item) >= strlen($root); $item = dirname($item)) {
            @chown($item, $owner);
            @chgrp($item, $group);
            if ($item === $root) {
                break;
            }
        }
    }

    /** Double-quoted dotenv value; the H5P service's parser handles the same escapes. */
    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value)) {
            return $value;
        }

        return '"' . str_replace(['\\', '"', "\n", '$'], ['\\\\', '\\"', '\\n', '\\$'], $value) . '"';
    }

    private function path(string $relative): string
    {
        return rtrim((string) $this->exportDir, '/') . '/' . $relative;
    }

    private function assertHost(string $host): void
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?$/', $host) || str_contains($host, '..')) {
            throw new RuntimeException("Invalid host {$host}");
        }
    }
}
