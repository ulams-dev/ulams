<?php

namespace Ulams\Ai\Dto;

final class DriverResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly string $stopReason,
        public readonly Usage $usage,
        public readonly ?string $requestId = null,
        public readonly ?string $refusalCategory = null,
    ) {
    }
}
