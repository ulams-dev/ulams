<?php

namespace Ulams\Tenancy\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Tenant commands usually run as root (`docker compose exec`), while php-fpm runs as the
 * owner of the storage directory and must read the tenant's Passport keys and write its
 * logs. Hands a tenant storage directory over to that owner.
 */
class StorageOwnership
{
    public static function handOver(string $directory): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || !is_dir($directory)) {
            return;
        }
        $owner = @fileowner(storage_path());
        $group = @filegroup(storage_path());
        if ($owner === false || $owner === 0) {
            return;
        }

        @chown($directory, $owner);
        @chgrp($directory, $group);
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            @chown($item->getPathname(), $owner);
            @chgrp($item->getPathname(), $group);
        }
    }
}
