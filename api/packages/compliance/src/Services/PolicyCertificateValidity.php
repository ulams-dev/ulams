<?php

namespace Ulams\Compliance\Services;

use Carbon\CarbonInterface;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Services\Contracts\CertificateValidityContract;

/**
 * Certificate validity from the course's compliance settings; courses without validity use the
 * templates-pdf default.
 */
class PolicyCertificateValidity implements CertificateValidityContract
{
    public function __construct(private CourseSettings $settings, private CertificateValidityContract $fallback)
    {
    }

    public function expiresAt(?string $assignableType, ?int $assignableId, CarbonInterface $issuedAt): ?CarbonInterface
    {
        if ($assignableType === Course::class && $assignableId) {
            $days = $this->settings->for($assignableId)->validity_days;
            if ($days) {
                return $issuedAt->copy()->addDays($days);
            }
        }

        return $this->fallback->expiresAt($assignableType, $assignableId, $issuedAt);
    }
}
