<?php

namespace EscolaLms\Reports\Services;

use EscolaLms\Reports\Exceptions\ExportNotExistsException;
use EscolaLms\Reports\Imports\Stats\Course\FinishedTopicsImport;
use EscolaLms\Reports\Services\Contracts\StatsServiceContract;
use EscolaLms\Reports\Stats\StatsContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StatsService implements StatsServiceContract
{
    private array $available_stats;

    public function __construct()
    {
        $this->available_stats = config('reports.stats');
    }

    public function calculate($model, array $selected_stats = []): array
    {
        $stats_to_calculate = $this->getAvailableStats($model);
        if (!empty($selected_stats)) {
            $stats_to_calculate = array_filter($stats_to_calculate, fn ($stat) => in_array($stat, $selected_stats));
        }

        $results = [];
        foreach ($stats_to_calculate as $stat) {
            $stat_instance = new $stat($model);
            assert($stat_instance instanceof StatsContract);
            $results[$stat] = $stat_instance->calculate();
        }
        return $results;
    }

    public function getAvailableStats($model = null): array
    {
        if (is_null($model)) {
            return $this->available_stats;
        }
        foreach ($this->available_stats as $class => $stats) {
            if (is_a($model, $class, true)) {
                return array_filter($stats, fn (string $stat) => class_exists($stat) && is_a($stat, StatsContract::class, true) && $stat::requiredPackagesInstalled());
            }
        }
        return [];
    }

    /**
     * @throws ExportNotExistsException
     */
    public function export($model, string $stat): BinaryFileResponse
    {
        $available = $this->getAvailableStats($model);
        $exportClass = 'EscolaLms\Reports\Exports\Stats\\' . Str::after($stat, 'Stats\\') . 'Export';

        if (!in_array($stat, $available) || !class_exists($exportClass)) {
            throw new ExportNotExistsException();
        }

        return Excel::download(
            (new $exportClass($model)),
            Str::snake(class_basename($stat)) . '_' . $model->getKey() . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function import($model, UploadedFile $file)
    {
        Excel::import(new FinishedTopicsImport($model), $file);
    }
}
