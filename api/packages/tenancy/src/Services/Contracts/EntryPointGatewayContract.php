<?php

namespace Ulams\Tenancy\Services\Contracts;

use Ulams\Tenancy\Exceptions\EntryPointException;

/**
 * What the tenant admin API needs from the platform table of entry points (ADR 0195). A tenant process has no
 * credentials for the platform database, so the production implementation asks a platform process; the
 * tenant is always named by the caller from its own configuration, never from request input.
 *
 * @phpstan-type Actor array{id: int|null, email: string|null}
 */
interface EntryPointGatewayContract
{
    /** @return list<array<string, mixed>> */
    public function list(string $slug): array;

    /**
     * @return array<string, mixed>
     *
     * @throws EntryPointException 404 when the tenant has no such entry point
     */
    public function show(string $slug, string $id): array;

    /**
     * @param array<string, mixed> $data
     * @param Actor|null $actor
     * @return array<string, mixed>
     *
     * @throws EntryPointException 422 with field errors
     */
    public function create(string $slug, array $data, ?array $actor): array;

    /**
     * @param array<string, mixed> $data
     * @param Actor|null $actor
     * @return array<string, mixed>
     *
     * @throws EntryPointException 404 or 422
     */
    public function update(string $slug, string $id, array $data, ?array $actor): array;

    /**
     * @param Actor|null $actor
     *
     * @throws EntryPointException 404
     */
    public function delete(string $slug, string $id, ?array $actor): void;

    /** @return list<array<string, mixed>> newest first */
    public function audit(string $slug, int $limit = 100): array;
}
