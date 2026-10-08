<?php

namespace EscolaLms\Templates\Services\Contracts;

interface TemplateVariablesServiceContract
{
    public function registerForChannel(string $variableClass, string $channelClass): void;
    public function listForChannel(string $class): array;

    public function sectionIsValid(string $variableClass, string $section, string $content): bool;
    public function contentIsValidForChannel(string $variableClass, string $channelClass, string $content): bool;

    public function missingVariablesInSection(string $variableClass, string $section, string $content): array;

    public function requiredSectionsForChannel(string $variableClass, string $channelClass): array;
    public function requiredVariablesForChannel(string $variableClass, string $channelClass): array;
}
