<?php

namespace Ulams\Reports\Services\Contracts;

interface ReportServiceContract
{
    public function getAvailableReportsForUser(): array;
}
