<?php

namespace EscolaLms\Templates\Core;

use EscolaLms\Core\Enums\BasicEnum;
use EscolaLms\Templates\Contracts\TemplateVariableContract;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Services\TemplateVariablesService;

abstract class AbstractTemplateVariableClass extends BasicEnum implements TemplateVariableContract
{
    public static function variables(): array
    {
        return array_merge(SettingsVariables::getSettingsKeys(), static::getValues());
    }

    public static function processTemplateAfterSaving(Template $template): Template
    {
        return $template;
    }

    public static function mockAllVariables(array $mockedVars): array
    {
        $allVariables = [];
        foreach ($mockedVars as $key => $variable) {
            foreach (TemplateVariablesService::convertVarNameToAllFormats($key) as $nKey) {
                $allVariables[$nKey] = $variable;
            }
        }
        return $allVariables;
    }
}
