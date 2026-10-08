<?php

namespace EscolaLms\Reports\Metrics;

use Cron\CronExpression;
use EscolaLms\Reports\Enums\ReportsPermissionsEnum;
use EscolaLms\Reports\Metrics\Contracts\MetricContract;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;

abstract class AbstractMetric implements MetricContract
{
    protected bool $history;
    protected int $limit;
    protected string $cron;

    public static function make(): MetricContract
    {
        return new static(config());
    }

    protected function __construct(Config $config)
    {
        $configuration = $config->get('reports.metric_configuration')[get_class($this)] ?? [];
        $this->history = $configuration['history'] ?? true;
        $this->limit = $configuration['limit'] ?? 10;
        $this->cron = $configuration['cron'] ?? '0 0 * * *';
        if (!CronExpression::isValidExpression($this->cron)) {
            $this->cron = '0 0 * * *';
        }
    }

    public function schedule(Schedule $schedule): void
    {
        if ($this->saveHistory()) {
            $schedule->call(fn () => $this->calculateAndStore())->cron($this->cronExpression());
        }
    }

    public function saveHistory(): bool
    {
        return $this->history;
    }

    public function cronExpression(): string
    {
        return $this->cron;
    }

    public function defaultLimit(): int
    {
        return $this->limit;
    }

    public static function requiredPermissions(): array
    {
        return [];
    }

    public static function requiredPermissionsCheck(): bool
    {
        $user = Auth::user();
        if ($user) {
            return $user->can(array_merge([ReportsPermissionsEnum::DISPLAY_REPORTS], static::requiredPermissions()));
        }
        return false;
    }

    public function getLimit(?int $limit): ?int
    {
        return $limit ? $limit < 0 ? null : $limit : $this->defaultLimit();
    }
}
