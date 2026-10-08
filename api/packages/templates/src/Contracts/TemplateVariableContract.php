<?php

namespace EscolaLms\Templates\Contracts;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\Templates\Models\Template;

interface TemplateVariableContract
{
    public static function variables(): array;
    public static function mockedVariables(?User $user = null): array;

    public static function variablesFromEvent(EventWrapper $event): array;

    public static function assignableClass(): ?string;

    public static function requiredSections(): array;
    public static function requiredVariables(): array;
    public static function requiredVariablesInSection(string $sectionKey): array;

    public static function defaultSectionsContent(): array;

    public static function processTemplateAfterSaving(Template $template): Template;
}
