<?php

namespace Ulams\Reports\Services\Contracts;

use Ulams\Reports\Exceptions\ExportNotExistsException;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface StatsServiceContract
{
    public function calculate($model, array $selected_stats = []): array;
    public function getAvailableStats($model = null): array;

    /**
     * @throws ExportNotExistsException
     */
    public function export($model, string $stat): BinaryFileResponse;
    public function import($model, UploadedFile $file);
}
