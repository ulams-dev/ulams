<?php

namespace EscolaLms\Reports\Services\Contracts;

interface ReportServiceContract
{
    public function getAvailableReportsForUser(): array;
}
