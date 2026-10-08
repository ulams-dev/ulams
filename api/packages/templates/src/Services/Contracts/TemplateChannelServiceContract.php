<?php

namespace EscolaLms\Templates\Services\Contracts;

interface TemplateChannelServiceContract
{
    public function register(string $class): void;
    public function list(): array;

    public function validateTemplateSections(string $class, array $sections): bool;
    public function lastValidationErrors(): array;
}
