<?php

namespace Ulams\TemplatesPdf\Services;

use Carbon\CarbonInterface;
use Ulams\TemplatesPdf\Services\Contracts\CertificateValidityContract;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

class ConfigCertificateValidity implements CertificateValidityContract
{
    public function expiresAt(?string $assignableType, ?int $assignableId, CarbonInterface $issuedAt): ?CarbonInterface
    {
        $days = (int) config(UlamsTemplatesPdfServiceProvider::CONFIG_KEY . '.validity_days', 0);

        return $days > 0 ? $issuedAt->copy()->addDays($days) : null;
    }
}
