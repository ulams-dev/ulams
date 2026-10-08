<?php

namespace EscolaLms\Reports\Metrics\Contracts;

use EscolaLms\Reports\Models\Report;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;

interface MetricContract
{
    public static function make(): MetricContract;

    public function defaultLimit(): int;
    public function cronExpression(): string;
    public function saveHistory(): bool;

    public function schedule(Schedule $schedule): void;

    public function calculate(?int $limit = null): Collection;
    public function calculateAndStore(?int $limit = null): Report;

    public static function requiredPackageInstalled(): bool;
    public function requiredPackage(): string;
    public static function requiredPermissions(): array;
    public static function requiredPermissionsCheck(): bool;
}
