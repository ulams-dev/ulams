<?php

namespace Ulams\Core\Services\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CspReportServiceContract
{
    /**
     * Stores the violations of one report request (either wire format).
     *
     * @param array<mixed> $payload decoded JSON body
     * @return int number of violations recorded
     */
    public function record(array $payload): int;

    public function list(?int $perPage): LengthAwarePaginator;

    /** Deletes the rows not seen for `$days` days; returns how many. */
    public function prune(int $days): int;
}
