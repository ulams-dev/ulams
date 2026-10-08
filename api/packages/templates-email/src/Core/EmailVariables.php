<?php

namespace Ulams\TemplatesEmail\Core;

use Ulams\Core\Models\User;
use Ulams\Templates\Contracts\TemplateVariableContract;
use Ulams\Templates\Core\AbstractTemplateVariableClass;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;
use Illuminate\Support\Str;

abstract class EmailVariables extends AbstractTemplateVariableClass implements TemplateVariableContract
{
    const VAR_APP_NAME     = '@VarAppName';

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(SettingsVariables::getSettingsValues(), [
            self::VAR_APP_NAME => config('app.name')
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(SettingsVariables::getSettingsValues(), [
            self::VAR_APP_NAME => config('app.name')
        ]);
    }

    public static function requiredSections(): array
    {
        return [];
    }

    public static function wrapWithMjml(string $content = ''): string
    {
        if (!Str::contains($content, ['<mj-text'])) {
            $content = '<mj-text>' . $content . '</mj-text>';
        }

        $template = config(
            UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.default_template',
            <<<MJML_TEMPLATE
            <mjml>
            <mj-body>
                <mj-section background-color="white">
                    <mj-column>
                        @VarTemplateContent
                    </mj-column>
                </mj-section>
            </mj-body>
            </mjml>
            MJML_TEMPLATE
        );

        return str_replace('@VarTemplateContent', $content, $template);
    }
}
