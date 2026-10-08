<?php

namespace Ulams\Tenancy\Services\Contracts;

/**
 * The laravel-multidomain registry: `.env.<host>` files, `config/domain.php` and the per-host
 * storage directories.
 */
interface DomainRegistryContract
{
    /**
     * Writes (or updates) `.env.<host>` from the platform `.env` plus the given values,
     * registers the host and creates its storage directories.
     *
     * @param array<string, string> $values
     */
    public function add(string $host, array $values): void;

    /**
     * Removes the env file, the storage directory and the registration.
     */
    public function remove(string $host): void;

    /**
     * True when the host is registered and has its own env file.
     */
    public function isRegistered(string $host): bool;

    public function storagePath(string $host): string;
}
