<?php

namespace Ulams\Lrs\Services\Contracts;

use Ulams\Lrs\Models\Access;

interface XapiStatementServiceContract
{
    /**
     * Validate and store statements sent through an access.
     *
     * @param array<int, array<string, mixed>> $statements decoded statements
     * @return string[] statement ids, in the order received
     */
    public function store(array $statements, Access $access): array;

    /**
     * A statement by id (or a voided statement by id when $voided is true).
     *
     * @return array<string, mixed>
     */
    public function find(string $id, Access $access, bool $voided = false): array;

    /**
     * Statements matching xAPI query parameters: agent, verb, activity, registration,
     * since, until, limit, ascending. Voided statements are left out.
     *
     * @param array<string, mixed> $filters
     * @return array{statements: array<int, array<string, mixed>>, more: string}
     */
    public function query(array $filters, Access $access, string $baseUrl): array;
}
