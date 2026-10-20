<?php

namespace Ulams\TemplatesPdf\Services\Contracts;

use Carbon\CarbonInterface;

/**
 * Decides when a newly issued certificate expires. The default reads one global setting; the
 * compliance package rebinds it to per-course validity.
 */
interface CertificateValidityContract
{
    /**
     * @return CarbonInterface|null null means the certificate never expires
     */
    public function expiresAt(?string $assignableType, ?int $assignableId, CarbonInterface $issuedAt): ?CarbonInterface;
}
